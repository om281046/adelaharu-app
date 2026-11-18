import UIKit
import AVFoundation
import Capacitor
#if canImport(FirebaseCore)
import FirebaseCore
#endif
#if canImport(FirebaseMessaging)
import FirebaseMessaging
#endif

@UIApplicationMain
class AppDelegate: UIResponder, UIApplicationDelegate {

    var window: UIWindow?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        // Meditațiile se aud și cu ecranul blocat / cu comutatorul de silențios pornit
        // (împreună cu UIBackgroundModes=audio). Comenzile de pe ecranul blocat vin din navigator.mediaSession (WebKit).
        try? AVAudioSession.sharedInstance().setCategory(.playback, mode: .spokenAudio, options: [])

        // Push: Firebase pornește doar dacă există GoogleService-Info.plist (îl copiază faza „GoogleService-Info.plist
        // (dacă există)” din proiect). Fără el aplicația merge normal, fără push: AhApp.info().push = false și puntea nu cere
        // notificări. FirebaseAppDelegateProxyEnabled = NO (Info.plist): tokenul APNs îl dăm noi, mai jos.
        #if canImport(FirebaseCore)
        if AhAppPlugin.areFirebaseConfig {
            FirebaseApp.configure()
        }
        #endif
        AppDelegate.faraBackup()
        return true
    }

    /// Ce ține de contul ei rămâne doar pe acest telefon, nu în copia iCloud / din computer: meditațiile descărcate
    /// (Library/NoCloud, deja „notSyncedLibrary”, dar îl marcăm oricum), datele WKWebView (localStorage-ul Ciclului meu,
    /// IndexedDB, cache) și cookie-urile contului. Preferences (UserDefaults: ah_ciclu, ah_descarcate…) nu se pot exclude
    /// cheie cu cheie: rămân în copia telefonului (criptată dacă ea a ales copia criptată); vezi docs/release.md.
    /// Se reaplică la fiecare pornire (WebKit poate recrea folderele).
    static func faraBackup() {
        let fm = FileManager.default
        guard let lib = fm.urls(for: .libraryDirectory, in: .userDomainMask).first else { return }
        for sub in ["NoCloud", "WebKit", "Cookies", "Caches/WebKit"] {
            var u = lib.appendingPathComponent(sub, isDirectory: true)
            guard fm.fileExists(atPath: u.path) else { continue }
            var v = URLResourceValues()
            v.isExcludedFromBackup = true
            try? u.setResourceValues(v)
        }
    }

    // Notificările push (@capacitor/push-notifications). Serverul trimite prin FCM HTTP v1, deci Capacitor trebuie să
    // primească tokenul FCM, nu tokenul APNs: cu Firebase pornit, îi dăm APNs-ul lui Firebase Messaging și trimitem
    // mai departe tokenul FCM (String). Fără Firebase, tokenul APNs (Data) merge la Capacitor ca înainte, dar puntea
    // nici nu cere push (AhApp.info().token = "apns") și nu l-ar trimite serverului (îl recunoaște după 64 de cifre hexa).
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        #if canImport(FirebaseMessaging)
        if FirebaseApp.app() != nil {
            let m = Messaging.messaging()
            m.apnsToken = deviceToken
            m.isAutoInitEnabled = true // ea a permis notificările (register): de acum Firebase poate ține tokenul la zi
            m.token { token, error in
                if let token = token, !token.isEmpty {
                    NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: token)
                } else {
                    NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications,
                                                    object: error ?? NSError(domain: "AhPush", code: 1, userInfo: [NSLocalizedDescriptionKey: "Firebase nu a dat tokenul FCM"]))
                }
            }
            return
        }
        #endif
        NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: deviceToken)
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications, object: error)
    }

    func application(_ application: UIApplication,
                     configurationForConnecting connectingSceneSession: UISceneSession,
                     options: UIScene.ConnectionOptions) -> UISceneConfiguration {
        let config = UISceneConfiguration(name: "Default Configuration",
                                          sessionRole: connectingSceneSession.role)
        config.delegateClass = SceneDelegate.self
        return config
    }
}
