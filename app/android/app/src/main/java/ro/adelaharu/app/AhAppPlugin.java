package ro.adelaharu.app;

import android.content.pm.PackageInfo;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * info() → { push, token, versiune, build, mediaNativ, eroare: { cod, la } }. token = "fcm": pe Android @capacitor/push-notifications dă
 * mereu tokenul FCM (puntea verifică tipul tokenului pe fiecare platformă).
 * push = true doar dacă aplicația are google-services.json (pluginul Gradle generează resursa google_app_id).
 * Fără Firebase, PushNotifications.register() ar închide aplicația pe Android, deci puntea de pe site
 * cere notificări DOAR când push e true.
 */
@CapacitorPlugin(name = "AhApp")
public class AhAppPlugin extends Plugin {

    /** Ultima eroare HTTP a paginii principale (MainActivity.AhWebViewClient): cod și moment (ms); 0 = niciuna. */
    private static volatile int eroareCod = 0;
    private static volatile long eroareLa = 0;

    static void noteazaEroare(int cod) {
        eroareCod = cod;
        eroareLa = System.currentTimeMillis();
    }

    static boolean arePush(android.content.Context c) {
        return c.getResources().getIdentifier("google_app_id", "string", c.getPackageName()) != 0;
    }

    @PluginMethod
    public void info(PluginCall call) {
        JSObject r = new JSObject();
        boolean push = arePush(getContext());
        r.put("push", push);
        r.put("token", push ? "fcm" : "");
        r.put("mediaNativ", true);
        // pagina offline: de ce s-a deschis (o eroare HTTP a site-ului = „site-ul e în lucru”, nu „fără internet”)
        JSObject er = new JSObject();
        er.put("cod", eroareCod);
        er.put("la", eroareLa);
        r.put("eroare", er);
        try {
            PackageInfo p = getContext().getPackageManager().getPackageInfo(getContext().getPackageName(), 0);
            r.put("versiune", p.versionName);
            r.put("build", String.valueOf(androidx.core.content.pm.PackageInfoCompat.getLongVersionCode(p)));
        } catch (Exception e) {
            r.put("versiune", "");
            r.put("build", "");
        }
        call.resolve(r);
    }
}
