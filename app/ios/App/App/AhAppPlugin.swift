import Foundation
import Capacitor
#if canImport(FirebaseMessaging)
import FirebaseMessaging
#endif

/// Pluginul mic al aplicației: îi spune punții de pe site ce poate telefonul.
/// `info()` → { push, versiune, build }. `push` e true doar dacă aplicația a fost construită cu
/// Firebase Messaging ȘI are GoogleService-Info.plist; altfel puntea nu cere permisiunea de notificări
/// (serverul trimite prin FCM, deci un token APNs simplu nu i-ar folosi la nimic).
@objc(AhAppPlugin)
public class AhAppPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "AhAppPlugin"
    public let jsName = "AhApp"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "info", returnType: CAPPluginReturnPromise)
    ]

    static var arePush: Bool {
        #if canImport(FirebaseMessaging)
        return Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil
        #else
        return false
        #endif
    }

    @objc func info(_ call: CAPPluginCall) {
        let v = Bundle.main.infoDictionary
        call.resolve([
            "push": AhAppPlugin.arePush,
            "versiune": v?["CFBundleShortVersionString"] as? String ?? "",
            "build": v?["CFBundleVersion"] as? String ?? "",
            // ecranul blocat: pe iOS îl face WebKit (navigator.mediaSession), fără plugin nativ
            "mediaNativ": false
        ])
    }
}

/// Controlerul Capacitor, cu pluginul de mai sus înregistrat (pluginurile din npm se înregistrează singure).
class AhBridgeViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(AhAppPlugin())
    }
}
