import Foundation
import UIKit
import WebKit
import Capacitor
import LocalAuthentication
import UserNotifications
import ImageIO
import WidgetKit
#if canImport(FirebaseCore)
import FirebaseCore
#endif
#if canImport(FirebaseMessaging)
import FirebaseMessaging
#endif

/// Pluginul mic al aplicației: îi spune punții de pe site ce poate telefonul și face ce site-ul nu poate.
/// `info()` → { push, token, versiune, build, mediaNativ }. `push` e true doar dacă aplicația a fost construită cu
/// Firebase Messaging ȘI are GoogleService-Info.plist; altfel puntea nu cere permisiunea de notificări
/// (serverul trimite prin FCM, deci un token APNs simplu nu i-ar folosi la nimic).
///
/// Setările aplicației (puntea le cere doar dacă metoda există — detectare, nu versiune):
///  - `widget({mod, titlu, sub, culoare, imagine, url})` → `{ok, imagine}`: datele widgetului (AhWidgetDate, App Group)
///    + poza descărcată aici (≤ 3 MB, micșorată la 600 px), apoi WidgetCenter reîncarcă widgetul;
///  - `biometrie()` → `{disponibil, tip: faceid|touchid|cod}`; `autentifica({motiv})` → `{ok, eroare?}`
///    (.deviceOwnerAuthentication: Face ID / Touch ID, cu codul telefonului ca rezervă);
///  - `insigna({n})` → cifra de pe pictogramă (doar dacă ea a permis notificările cu insignă);
///  - `tema({seara})` → bara de sus (text deschis seara) și fundalul din spatele paginii.
@objc(AhAppPlugin)
public class AhAppPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "AhAppPlugin"
    public let jsName = "AhApp"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "info", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "widget", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "biometrie", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "autentifica", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "insigna", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "tema", returnType: CAPPluginReturnPromise)
    ]

    /// fondul paginii: ziua (Spațiul meu, sn235) și seara (aspectul de seară, sn266; același cu splash-ul de seară)
    static let fundalZi = UIColor(red: 0xFB / 255.0, green: 0xF6 / 255.0, blue: 0xF3 / 255.0, alpha: 1)
    static let fundalSeara = UIColor(red: 0x22 / 255.0, green: 0x19 / 255.0, blue: 0x22 / 255.0, alpha: 1)
    /// poza widgetului: cel mult atât de descărcat și atât de mare pe latura lungă (memoria unui widget e mică)
    static let maxOctetiImagine = 3 * 1024 * 1024
    static let maxPixeliImagine: CGFloat = 600

    /// GoogleService-Info.plist e în aplicație (nu e în repo: îl pune CI-ul din secret sau proprietara, local).
    static var areFirebaseConfig: Bool {
        return Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil
    }

    /// Push doar cu Firebase Messaging compilat ȘI pornit (altfel tokenul ar fi APNs, pe care serverul nu-l folosește).
    static var arePush: Bool {
        #if canImport(FirebaseMessaging)
        return areFirebaseConfig && FirebaseApp.app() != nil
        #else
        return false
        #endif
    }

    @objc func info(_ call: CAPPluginCall) {
        let v = Bundle.main.infoDictionary
        call.resolve([
            "push": AhAppPlugin.arePush,
            // ce token dă „registration”: "fcm" (Firebase Messaging) sau "apns" (fără Firebase); puntea trimite doar FCM
            "token": AhAppPlugin.arePush ? "fcm" : "apns",
            "versiune": v?["CFBundleShortVersionString"] as? String ?? "",
            "build": v?["CFBundleVersion"] as? String ?? "",
            // ecranul blocat: pe iOS îl face WebKit (navigator.mediaSession), fără plugin nativ
            "mediaNativ": false,
            // eroarea HTTP (≥ 500) care a deschis pagina offline: cod + momentul (ms), ca ea să spună „site-ul e în lucru”
            "eroare": ["cod": AhNavigare.ultimaEroare.cod, "la": AhNavigare.ultimaEroare.la]
        ])
    }

    // MARK: - Widgetul de pe ecranul principal

    @objc func widget(_ call: CAPPluginCall) {
        var d = AhWidgetDate.curata(mod: call.getString("mod"), titlu: call.getString("titlu"), sub: call.getString("sub"),
                                    culoare: call.getString("culoare"), url: call.getString("url"))
        guard AhWidgetDate.depozit != nil, AhWidgetDate.folder != nil else {
            // fără App Group în semnătură (profil vechi): widgetul n-ar avea de unde citi
            call.resolve(["ok": false, "eroare": "grup"])
            return
        }
        let imagine = call.getString("imagine") ?? ""
        // poza doar la meditație; la fază și „nimic” se șterge orice poză veche (nimic în plus despre ea în container)
        guard d.mod == "meditatie", let u = URL(string: imagine), u.scheme?.lowercased() == "https" else {
            AhAppPlugin.stergeImagine()
            d.areImagine = false
            AhAppPlugin.publica(d)
            call.resolve(["ok": true, "imagine": false])
            return
        }
        AhAppPlugin.descarcaImagine(u) { reusit in
            d.areImagine = reusit
            if !reusit { AhAppPlugin.stergeImagine() }
            AhAppPlugin.publica(d)
            call.resolve(["ok": true, "imagine": reusit])
        }
    }

    static func publica(_ d: AhWidgetDate) {
        d.scrie()
        WidgetCenter.shared.reloadTimelines(ofKind: AhWidgetDate.tip)
    }

    static func stergeImagine() {
        if let u = AhWidgetDate.caleImagine { try? FileManager.default.removeItem(at: u) }
    }

    /// Descarcă poza (doar https, doar image/*, ≤ 3 MB), o micșorează la 600 px (ImageIO, fără s-o decodeze întreagă)
    /// și o scrie JPEG în containerul App Group. Widgetul doar o citește de pe disc.
    static func descarcaImagine(_ u: URL, gata: @escaping (Bool) -> Void) {
        AhDescarcare(u, limita: maxOctetiImagine) { date in
            guard let date = date, let sursa = CGImageSourceCreateWithData(date as CFData, nil) else { gata(false); return }
            let optiuni: [CFString: Any] = [
                kCGImageSourceCreateThumbnailFromImageAlways: true,
                kCGImageSourceCreateThumbnailWithTransform: true,
                kCGImageSourceShouldCacheImmediately: true,
                kCGImageSourceThumbnailMaxPixelSize: maxPixeliImagine
            ]
            guard let cg = CGImageSourceCreateThumbnailAtIndex(sursa, 0, optiuni as CFDictionary),
                  let jpeg = UIImage(cgImage: cg).jpegData(compressionQuality: 0.85),
                  let dest = AhWidgetDate.caleImagine else { gata(false); return }
            do {
                try jpeg.write(to: dest, options: [.atomic])
                gata(true)
            } catch {
                gata(false)
            }
        }.porneste()
    }

    // MARK: - Face ID / Touch ID / codul telefonului (Ciclul meu blocat)

    @objc func biometrie(_ call: CAPPluginCall) {
        let ctx = LAContext()
        var eroare: NSError?
        let disponibil = ctx.canEvaluatePolicy(.deviceOwnerAuthentication, error: &eroare)
        // biometryType se completează abia după un canEvaluatePolicy (oricare politică)
        _ = ctx.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
        var tip = "cod"
        switch ctx.biometryType {
        case .faceID: tip = "faceid"
        case .touchID: tip = "touchid"
        default: tip = "cod"   // fără biometrie (sau Optic ID, care nu e pe iPhone): codul telefonului
        }
        call.resolve(["disponibil": disponibil, "tip": tip])
    }

    @objc func autentifica(_ call: CAPPluginCall) {
        let motiv = AhWidgetDate.scurteaza(call.getString("motiv"), 120)
        let ctx = LAContext()
        ctx.localizedCancelTitle = "Renunță"
        var eroare: NSError?
        guard ctx.canEvaluatePolicy(.deviceOwnerAuthentication, error: &eroare) else {
            call.resolve(["ok": false, "eroare": AhAppPlugin.motivEroare(eroare)])
            return
        }
        // .deviceOwnerAuthentication: Face ID / Touch ID, iar dacă nu merge, codul telefonului (nu o blocăm afară)
        ctx.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: motiv.isEmpty ? "Deblochează Ciclul meu" : motiv) { ok, e in
            if ok {
                call.resolve(["ok": true])
            } else {
                call.resolve(["ok": false, "eroare": AhAppPlugin.motivEroare(e as NSError?)])
            }
        }
    }

    /// Erorile LocalAuthentication → cuvinte stabile pentru punte: anulat | blocat | indisponibil | eroare.
    static func motivEroare(_ e: NSError?) -> String {
        guard let e = e, e.domain == LAErrorDomain, let c = LAError.Code(rawValue: e.code) else { return "eroare" }
        switch c {
        case .userCancel, .systemCancel, .appCancel, .userFallback: return "anulat"
        case .biometryLockout: return "blocat"
        case .passcodeNotSet, .biometryNotAvailable, .biometryNotEnrolled: return "indisponibil"
        default: return "eroare"
        }
    }

    // MARK: - Cifra de pe pictogramă

    @objc func insigna(_ call: CAPPluginCall) {
        let n = max(0, call.getInt("n") ?? 0)
        let centru = UNUserNotificationCenter.current()
        centru.getNotificationSettings { s in
            // fără permisiunea de notificări (sau cu insigna oprită de ea în Configurări), nu încercăm nimic
            let permis = (s.authorizationStatus == .authorized || s.authorizationStatus == .provisional || s.authorizationStatus == .ephemeral)
                && s.badgeSetting == .enabled
            guard permis else {
                call.resolve(["ok": false])
                return
            }
            if #available(iOS 16.0, *) {
                centru.setBadgeCount(n) { e in call.resolve(["ok": e == nil]) }
            } else {
                DispatchQueue.main.async {
                    UIApplication.shared.applicationIconBadgeNumber = n
                    call.resolve(["ok": true])
                }
            }
        }
    }

    // MARK: - Tema (ziua / seara)

    /// Bara de sus: text închis ziua, deschis seara; fundalul din spatele WebView-ului (vizibil la derulare peste
    /// margini și cât se încarcă pagina) după temă. StatusBar.setStyle din JS ar face același lucru pentru bară;
    /// aici e într-un singur apel și se potrivește și fundalul.
    @objc func tema(_ call: CAPPluginCall) {
        let seara = call.getBool("seara") ?? false
        DispatchQueue.main.async { [weak self] in
            guard let bridge = self?.bridge else { call.resolve(["ok": false]); return }
            let fundal = seara ? AhAppPlugin.fundalSeara : AhAppPlugin.fundalZi
            bridge.statusBarStyle = seara ? .lightContent : .darkContent
            bridge.viewController?.view.backgroundColor = fundal
            bridge.webView?.backgroundColor = fundal
            bridge.webView?.scrollView.backgroundColor = fundal
            call.resolve(["ok": true])
        }
    }
}

/// O descărcare mică, cu limită: se oprește de cum mărimea anunțată sau octeții primiți trec de `limita`, ori răspunsul
/// nu e o imagine (URLSession.shared.dataTask ar aduce tot în memorie înainte să putem verifica).
final class AhDescarcare: NSObject, URLSessionDataDelegate {
    private let url: URL
    private let limita: Int
    private var gata: ((Data?) -> Void)?
    private var date = Data()
    private var sesiune: URLSession?

    init(_ url: URL, limita: Int, gata: @escaping (Data?) -> Void) {
        self.url = url
        self.limita = limita
        self.gata = gata
        super.init()
    }

    func porneste() {
        let c = URLSessionConfiguration.ephemeral
        c.timeoutIntervalForRequest = 15
        c.timeoutIntervalForResource = 30
        // sesiunea ține delegatul (pe noi) tare până la finishTasksAndInvalidate
        let s = URLSession(configuration: c, delegate: self, delegateQueue: nil)
        sesiune = s
        var r = URLRequest(url: url)
        r.setValue("image/*", forHTTPHeaderField: "Accept")
        s.dataTask(with: r).resume()
        s.finishTasksAndInvalidate()
    }

    private func termina(_ d: Data?) {
        let g = gata
        gata = nil
        sesiune = nil
        g?(d)
    }

    func urlSession(_ session: URLSession, dataTask: URLSessionDataTask, didReceive response: URLResponse,
                    completionHandler: @escaping (URLSession.ResponseDisposition) -> Void) {
        guard let h = response as? HTTPURLResponse, (200..<300).contains(h.statusCode),
              (h.mimeType ?? "").lowercased().hasPrefix("image/"),
              h.expectedContentLength <= Int64(limita) else {
            completionHandler(.cancel)
            return
        }
        completionHandler(.allow)
    }

    func urlSession(_ session: URLSession, dataTask: URLSessionDataTask, didReceive data: Data) {
        date.append(data)
        if date.count > limita {
            date = Data()
            dataTask.cancel()
        }
    }

    func urlSession(_ session: URLSession, task: URLSessionTask, didCompleteWithError error: Error?) {
        termina(error == nil && !date.isEmpty && date.count <= limita ? date : nil)
    }
}

/// Controlerul Capacitor, cu pluginul de mai sus înregistrat (pluginurile din npm se înregistrează singure)
/// și cu filtrul AhNavigare pus între WKWebView și WebViewDelegationHandler.
class AhBridgeViewController: CAPBridgeViewController {
    /// webView.navigationDelegate e `weak`: filtrul trăiește cât controlerul.
    private var navigare: AhNavigare?

    /// Video (iframe-urile Vimeo din sala de curs): Capacitor 8 pune deja allowsInlineMediaPlayback, AirPlay,
    /// mediaTypesRequiringUserActionForPlayback = [] și isElementFullscreenEnabled (iOS 15.4+). Adăugăm explicit
    /// Picture in Picture (implicit pornit pe iPhone, dar nu ne bazăm pe implicit). Ecranul complet al videoului îl
    /// prezintă WebKit într-un controler propriu, care se poate roti (Info.plist permite și peisaj pe iPhone), chiar
    /// dacă ecranul aplicației rămâne vertical (supportedInterfaceOrientations mai jos). Chromecast nu există într-un
    /// WebView; pe iPhone, AirPlay (butonul din playerul Vimeo / din ecranul complet).
    override func webViewConfiguration(for instanceConfiguration: InstanceConfiguration) -> WKWebViewConfiguration {
        let c = super.webViewConfiguration(for: instanceConfiguration)
        c.allowsInlineMediaPlayback = true
        c.allowsAirPlayForMediaPlayback = true
        c.allowsPictureInPictureMediaPlayback = true
        return c
    }

    /// Aplicația (Spațiul meu) rămâne verticală pe iPhone; doar videoul pe tot ecranul se rotește (vezi mai sus).
    /// Info.plist păstrează peisajul în UISupportedInterfaceOrientations tocmai ca să-l poată folosi playerul.
    override var supportedInterfaceOrientations: UIInterfaceOrientationMask {
        return UIDevice.current.userInterfaceIdiom == .pad ? super.supportedInterfaceOrientations : .portrait
    }

    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(AhAppPlugin())
        // capacitorDidLoad vine din loadView, înainte de loadWebView (viewDidLoad): prima încărcare trece deja prin filtru
        if let wv = webView, let capacitor = wv.navigationDelegate {
            // puntea JS → nativ („bridge”, WebViewDelegationHandler) doar pentru pagina principală a site-ului (AhPuntePazita)
            if let handler = capacitor as? WKScriptMessageHandler {
                let cc = (capacitor as? WebViewDelegationHandler)?.contentController ?? wv.configuration.userContentController
                cc.removeScriptMessageHandler(forName: AhPuntePazita.nume)
                cc.add(AhPuntePazita(handler, schemaLocala: bridge?.config.localURL.scheme ?? "capacitor"), name: AhPuntePazita.nume)
            }
            let n = AhNavigare(capacitor, paginaOffline: bridge?.config.errorPathURL)
            navigare = n
            wv.navigationDelegate = n
        }
    }
}

/// Capacitor (WebViewDelegationHandler) primește mesajele punții („bridge”) din ORICE cadru și de pe orice origine:
/// un iframe (Vimeo, un embed) sau o pagină de pe altă gazdă ajunsă în WebView ar putea chema pluginurile (Preferences cu
/// datele ciclului, Filesystem, push). Aici le dăm mai departe doar dacă vin din cadrul principal, de pe
/// https://www.adelaharu.ro (Spațiul meu) sau de pe pagina offline locală (capacitor://www.adelaharu.ro/offline.html).
/// Restul se ignoră (apelul din pagină nu primește răspuns). Se pune în capacitorDidLoad, înainte de prima încărcare.
final class AhPuntePazita: NSObject, WKScriptMessageHandler {
    static let nume = "bridge"     // WebViewDelegationHandler.handlerName (Capacitor 8)
    static let gazda = "www.adelaharu.ro"
    /// WKUserContentController ține handlerul tare; pe cel al lui Capacitor îl ține bridge-ul.
    private weak var capacitor: (NSObject & WKScriptMessageHandler)?
    private let capacitorTare: WKScriptMessageHandler?
    private let schemaLocala: String

    init(_ capacitor: WKScriptMessageHandler, schemaLocala: String) {
        if let o = capacitor as? (NSObject & WKScriptMessageHandler) {
            self.capacitor = o
            self.capacitorTare = nil
        } else {
            self.capacitorTare = capacitor
        }
        self.schemaLocala = schemaLocala.lowercased()
        super.init()
    }

    static func permis(mainFrame: Bool, protocol p: String, host: String, schemaLocala: String) -> Bool {
        guard mainFrame, host.lowercased() == gazda else { return false }
        let p = p.lowercased()
        return p == "https" || p == schemaLocala
    }

    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        let o = message.frameInfo.securityOrigin
        guard AhPuntePazita.permis(mainFrame: message.frameInfo.isMainFrame, protocol: o.protocol, host: o.host, schemaLocala: schemaLocala) else {
            CAPLog.print("⚡️  AhPuntePazita: mesaj ignorat de pe", o.protocol, o.host, message.frameInfo.isMainFrame ? "(cadrul principal)" : "(iframe)")
            return
        }
        (capacitor ?? capacitorTare)?.userContentController(userContentController, didReceive: message)
    }
}

/// Capacitor (WebViewDelegationHandler) încarcă pagina offline la ORICE didFail / didFailProvisionalNavigation,
/// fără să se uite la eroare. Două „erori” nu înseamnă lipsa internetului:
///  - NSURLErrorCancelled (-999): o navigare nouă a pornit înainte să se termine cea veche (a doua atingere rapidă,
///    linkul universal sau notificarea deschise cât încă se încarcă Spațiul meu, mergi() din punte);
///  - WebKitErrorDomain 102 („frame load interrupted”): navigarea a fost oprită de o decizie (link spre un fișier pe
///    care WebView-ul nu-l afișează, adresă trimisă de Capacitor în afara aplicației).
/// Pe acestea le înghițim (altfel cine are internet ar vedea „Nu avem internet acum”, iar încărcarea paginii offline
/// ar anula chiar navigarea dorită). Tot restul merge neschimbat la Capacitor.
///
/// Răspunsurile 5xx ale site-ului (2.3.0): WKWebView ar desena pagina de eroare a serverului (sau una albă) și nimic nu
/// ar duce la pagina offline. Pentru cadrul principal de pe site, un răspuns cu cod ≥ 500 se anulează și se deschide
/// pagina offline cu ?cod=<cod> (plus AhNavigare.ultimaEroare, citită prin AhApp.info()), ca ea să arate „Site-ul e în
/// lucru” în loc de „Nu avem internet”. Anularea dă WebKitErrorDomain 102, pe care filtrul de mai jos îl înghite.
final class AhNavigare: NSObject, WKNavigationDelegate {
    /// Handlerul Capacitor e ținut de bridge; aici doar îl folosim.
    private weak var capacitor: WKNavigationDelegate?
    private let paginaOffline: URL?
    static let gazde: Set<String> = ["www.adelaharu.ro", "adelaharu.ro"]
    /// Ultima eroare HTTP a cadrului principal (cod, ms de la 1970); 0 = niciuna.
    static var ultimaEroare: (cod: Int, la: Double) = (0, 0)

    init(_ capacitor: WKNavigationDelegate, paginaOffline: URL? = nil) {
        self.capacitor = capacitor
        self.paginaOffline = paginaOffline
        super.init()
    }

    /// Pagina offline cu motivul: capacitor://www.adelaharu.ro/offline.html?cod=503 (serverul local ia doar calea).
    static func adresaEroare(_ baza: URL, cod: Int) -> URL {
        var c = URLComponents(url: baza, resolvingAgainstBaseURL: false)
        c?.queryItems = [URLQueryItem(name: "cod", value: String(cod))]
        return c?.url ?? baza
    }

    func webView(_ webView: WKWebView, decidePolicyFor navigationResponse: WKNavigationResponse, decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
        if navigationResponse.isForMainFrame,
           let h = navigationResponse.response as? HTTPURLResponse, h.statusCode >= 500,
           let gazda = h.url?.host?.lowercased(), AhNavigare.gazde.contains(gazda),
           let offline = paginaOffline {
            AhNavigare.ultimaEroare = (h.statusCode, Date().timeIntervalSince1970 * 1000)
            decisionHandler(.cancel)
            webView.load(URLRequest(url: AhNavigare.adresaEroare(offline, cod: h.statusCode)))
            return
        }
        // Capacitor 8 (WebViewDelegationHandler) nu implementează decidePolicyFor navigationResponse: restul trece neschimbat.
        // La o actualizare a lui Capacitor: grep „navigationResponse” în WebViewDelegationHandler.swift (vezi app/README.md).
        decisionHandler(.allow)
    }

    static func nuEOffline(_ error: Error) -> Bool {
        let e = error as NSError
        if e.domain == NSURLErrorDomain && e.code == NSURLErrorCancelled { return true }
        if e.domain == "WebKitErrorDomain" && e.code == 102 { return true }
        return false
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        if AhNavigare.nuEOffline(error) { return }
        capacitor?.webView?(webView, didFail: navigation, withError: error)
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        if AhNavigare.nuEOffline(error) { return }
        capacitor?.webView?(webView, didFailProvisionalNavigation: navigation, withError: error)
    }

    // Restul metodelor (decidePolicyFor, didFinish, didReceive challenge, procesul terminat…) ajung direct la Capacitor.
    override func responds(to aSelector: Selector!) -> Bool {
        if super.responds(to: aSelector) { return true }
        return capacitor?.responds(to: aSelector) ?? false
    }

    override func forwardingTarget(for aSelector: Selector!) -> Any? {
        if let c = capacitor, c.responds(to: aSelector) { return c }
        return super.forwardingTarget(for: aSelector)
    }
}
