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
            let n = AhNavigare(capacitor)
            navigare = n
            wv.navigationDelegate = n
        }
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
