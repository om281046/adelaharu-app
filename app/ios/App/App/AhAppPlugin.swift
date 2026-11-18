import Foundation
import WebKit
import Capacitor
#if canImport(FirebaseCore)
import FirebaseCore
#endif
#if canImport(FirebaseMessaging)
import FirebaseMessaging
#endif

/// Pluginul mic al aplicației: îi spune punții de pe site ce poate telefonul.
/// `info()` → { push, token, versiune, build, mediaNativ }. `push` e true doar dacă aplicația a fost construită cu
/// Firebase Messaging ȘI are GoogleService-Info.plist; altfel puntea nu cere permisiunea de notificări
/// (serverul trimite prin FCM, deci un token APNs simplu nu i-ar folosi la nimic).
@objc(AhAppPlugin)
public class AhAppPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "AhAppPlugin"
    public let jsName = "AhApp"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "info", returnType: CAPPluginReturnPromise)
    ]

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
            "mediaNativ": false
        ])
    }
}

/// Controlerul Capacitor, cu pluginul de mai sus înregistrat (pluginurile din npm se înregistrează singure)
/// și cu filtrul AhNavigare pus între WKWebView și WebViewDelegationHandler.
class AhBridgeViewController: CAPBridgeViewController {
    /// webView.navigationDelegate e `weak`: filtrul trăiește cât controlerul.
    private var navigare: AhNavigare?

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
            let n = AhNavigare(capacitor)
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
final class AhNavigare: NSObject, WKNavigationDelegate {
    /// Handlerul Capacitor e ținut de bridge; aici doar îl folosim.
    private weak var capacitor: WKNavigationDelegate?

    init(_ capacitor: WKNavigationDelegate) {
        self.capacitor = capacitor
        super.init()
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
