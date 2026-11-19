package ro.adelaharu.app;

import android.app.KeyguardManager;
import android.app.Notification;
import android.app.NotificationManager;
import android.content.Context;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.os.Build;
import android.service.notification.StatusBarNotification;
import android.view.Window;
import android.webkit.WebView;
import androidx.appcompat.app.AppCompatActivity;
import androidx.biometric.BiometricManager;
import androidx.biometric.BiometricPrompt;
import androidx.core.content.ContextCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Logger;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * info() → { push, token, versiune, build, mediaNativ, eroare: { cod, la } }. token = "fcm": pe Android @capacitor/push-notifications dă
 * mereu tokenul FCM (puntea verifică tipul tokenului pe fiecare platformă).
 * push = true doar dacă aplicația are google-services.json (pluginul Gradle generează resursa google_app_id).
 * Fără Firebase, PushNotifications.register() ar închide aplicația pe Android, deci puntea de pe site
 * cere notificări DOAR când push e true.
 *
 * Setările aplicației (puntea le cere doar dacă metoda există):
 *  - widget({mod, titlu, sub, culoare, imagine, url}) → {ok, imagine}: datele widgetului (AhWidget / AhWidgetDate) și
 *    poza descărcată aici (https, ≤ 3 MB, micșorată la 600 px) în files/widget/; apoi widgeturile se redesenează;
 *  - biometrie() → {disponibil, tip: amprenta|fata|cod}; autentifica({motiv}) → {ok, eroare?} (BiometricPrompt,
 *    amprentă / față cu codul telefonului ca rezervă);
 *  - insigna({n}) → n = 0 șterge notificările afișate (insigna lansatorului urmează notificările; cea a meditației rămâne);
 *  - tema({seara}) → textul barelor de sistem (deschis seara) și fundalul din spatele paginii.
 */
@CapacitorPlugin(name = "AhApp")
public class AhAppPlugin extends Plugin {

    /** Ultima eroare HTTP a paginii principale (MainActivity.AhWebViewClient): cod și moment (ms); 0 = niciuna. */
    private static volatile int eroareCod = 0;
    private static volatile long eroareLa = 0;

    /** fondul paginii: ziua (Spațiul meu, sn235) și seara (aspectul de seară, sn266; același cu splash-ul de seară) */
    static final int FUNDAL_ZI = 0xFFFBF6F3;
    static final int FUNDAL_SEARA = 0xFF221922;
    /** poza widgetului: cel mult atât de descărcat și atât de mare pe latura lungă (RemoteViews trece prin IPC) */
    static final int MAX_OCTETI_IMAGINE = 3 * 1024 * 1024;
    static final int MAX_PIXELI_IMAGINE = 600;
    /** Face ID / amprentă / față, iar dacă nu merge, codul telefonului. BIOMETRIC_WEAK include STRONG (biții lui
     *  BIOMETRIC_STRONG sunt în BIOMETRIC_WEAK), deci STRONG|WEAK|DEVICE_CREDENTIAL = WEAK|DEVICE_CREDENTIAL; combinația
     *  e permisă pe toate versiunile (doar DEVICE_CREDENTIAL singur sub API 30 și STRONG|DEVICE_CREDENTIAL pe 28–29 nu sunt). */
    static final int AUTENTIFICARI = BiometricManager.Authenticators.BIOMETRIC_WEAK | BiometricManager.Authenticators.DEVICE_CREDENTIAL;

    private static final ExecutorService descarcari = Executors.newSingleThreadExecutor();

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

    // ——— Widgetul de pe ecranul principal ———

    @PluginMethod
    public void widget(PluginCall call) {
        final Context c = getContext().getApplicationContext();
        final AhWidgetDate d = AhWidgetDate.curata(call.getString("mod"), call.getString("titlu"), call.getString("sub"),
            call.getString("culoare"), call.getString("url"), System.currentTimeMillis());
        final String imagine = call.getString("imagine", "");
        descarcari.execute(() -> {
            boolean areImagine = false;
            File f = AhWidget.fisierImagine(c);
            // poza doar la meditație; la fază și „nimic” se șterge orice poză veche
            if ("meditatie".equals(d.mod) && imagine != null && imagine.startsWith("https://")) {
                areImagine = descarcaImagine(imagine, f);
            }
            if (!areImagine && f.exists() && !f.delete()) Logger.warn("AhApp.widget: poza veche nu s-a putut șterge");
            d.areImagine = areImagine;
            AhWidget.salveaza(c, d);
            AhWidget.actualizeazaToate(c);
            JSObject r = new JSObject();
            r.put("ok", true);
            r.put("imagine", areImagine);
            call.resolve(r);
        });
    }

    /** Descarcă poza (https, image/*, ≤ 3 MB), o micșorează la ≤ 600 px și o scrie JPEG în fișierul widgetului. */
    static boolean descarcaImagine(String adresa, File dest) {
        HttpURLConnection con = null;
        try {
            URL u = new URL(adresa);
            if (!"https".equalsIgnoreCase(u.getProtocol())) return false;
            con = (HttpURLConnection) u.openConnection();
            con.setConnectTimeout(15000);
            con.setReadTimeout(15000);
            con.setInstanceFollowRedirects(true); // HttpURLConnection nu urmează redirecționări de la https la http
            con.setRequestProperty("Accept", "image/*");
            int cod = con.getResponseCode();
            String tip = con.getContentType();
            if (cod < 200 || cod >= 300 || tip == null || !tip.toLowerCase(java.util.Locale.ROOT).startsWith("image/")) return false;
            long anuntat = con.getContentLengthLong();
            if (anuntat > MAX_OCTETI_IMAGINE) return false;
            ByteArrayOutputStream b = new ByteArrayOutputStream();
            try (InputStream in = con.getInputStream()) {
                byte[] buf = new byte[16384];
                int n;
                while ((n = in.read(buf)) != -1) {
                    b.write(buf, 0, n);
                    if (b.size() > MAX_OCTETI_IMAGINE) return false;
                }
            }
            byte[] date = b.toByteArray();
            BitmapFactory.Options o = new BitmapFactory.Options();
            o.inJustDecodeBounds = true;
            BitmapFactory.decodeByteArray(date, 0, date.length, o);
            if (o.outWidth <= 0 || o.outHeight <= 0) return false;
            int esantion = 1;
            while (Math.max(o.outWidth, o.outHeight) / (esantion * 2) >= MAX_PIXELI_IMAGINE) esantion *= 2;
            o = new BitmapFactory.Options();
            o.inSampleSize = esantion;
            Bitmap bmp = BitmapFactory.decodeByteArray(date, 0, date.length, o);
            if (bmp == null) return false;
            int latura = Math.max(bmp.getWidth(), bmp.getHeight());
            if (latura > MAX_PIXELI_IMAGINE) {
                float k = (float) MAX_PIXELI_IMAGINE / latura;
                Bitmap mic = Bitmap.createScaledBitmap(bmp, Math.round(bmp.getWidth() * k), Math.round(bmp.getHeight() * k), true);
                if (mic != bmp) bmp.recycle();
                bmp = mic;
            }
            File dir = dest.getParentFile();
            if (dir != null && !dir.exists() && !dir.mkdirs()) return false;
            File tmp = new File(dest.getPath() + ".tmp");
            try (FileOutputStream out = new FileOutputStream(tmp)) {
                if (!bmp.compress(Bitmap.CompressFormat.JPEG, 85, out)) return false;
            } finally {
                bmp.recycle();
            }
            return tmp.renameTo(dest);
        } catch (Exception e) {
            Logger.warn("AhApp.widget: poza nu s-a descărcat: " + e.getMessage());
            return false;
        } finally {
            if (con != null) con.disconnect();
        }
    }

    // ——— Amprentă / față / codul telefonului (Ciclul meu blocat) ———

    @PluginMethod
    public void biometrie(PluginCall call) {
        Context c = getContext();
        BiometricManager bm = BiometricManager.from(c);
        boolean bio = bm.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK) == BiometricManager.BIOMETRIC_SUCCESS;
        boolean disponibil = bm.canAuthenticate(AUTENTIFICARI) == BiometricManager.BIOMETRIC_SUCCESS;
        if (!disponibil) {
            // rezervă: telefonul are cod / model / PIN (BiometricPrompt îl poate cere și fără biometrie)
            KeyguardManager km = (KeyguardManager) c.getSystemService(Context.KEYGUARD_SERVICE);
            disponibil = km != null && km.isDeviceSecure();
        }
        String tip = "cod";
        if (bio) {
            PackageManager pm = c.getPackageManager();
            if (pm.hasSystemFeature(PackageManager.FEATURE_FINGERPRINT)) {
                tip = "amprenta";
            } else if (Build.VERSION.SDK_INT >= 29
                && (pm.hasSystemFeature(PackageManager.FEATURE_FACE) || pm.hasSystemFeature(PackageManager.FEATURE_IRIS))) {
                tip = "fata";
            }
        }
        JSObject r = new JSObject();
        r.put("disponibil", disponibil);
        r.put("tip", tip);
        call.resolve(r);
    }

    @PluginMethod
    public void autentifica(PluginCall call) {
        final String motiv = AhWidgetDate.scurteaza(call.getString("motiv", ""), 120);
        final AppCompatActivity a = getActivity(); // AppCompatActivity ⊂ FragmentActivity, cum cere BiometricPrompt
        if (a == null) {
            rezultatAutentificare(call, false, "eroare");
            return;
        }
        a.runOnUiThread(() -> {
            final AtomicBoolean gata = new AtomicBoolean(false);
            BiometricPrompt bp = new BiometricPrompt(a, ContextCompat.getMainExecutor(a), new BiometricPrompt.AuthenticationCallback() {
                @Override
                public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                    if (gata.compareAndSet(false, true)) rezultatAutentificare(call, true, null);
                }

                @Override
                public void onAuthenticationError(int cod, CharSequence mesaj) {
                    if (gata.compareAndSet(false, true)) rezultatAutentificare(call, false, motivEroare(cod));
                }
                // onAuthenticationFailed = o încercare nereușită; fereastra rămâne deschisă, nu răspundem încă
            });
            BiometricPrompt.PromptInfo info = new BiometricPrompt.PromptInfo.Builder()
                .setTitle(motiv.isEmpty() ? "Deblochează Ciclul meu" : motiv)
                .setAllowedAuthenticators(AUTENTIFICARI) // cu DEVICE_CREDENTIAL nu se pune buton „Renunță” (îl dă sistemul)
                .setConfirmationRequired(false)
                .build();
            try {
                bp.authenticate(info);
            } catch (Exception e) {
                if (gata.compareAndSet(false, true)) rezultatAutentificare(call, false, "indisponibil");
            }
        });
    }

    private static void rezultatAutentificare(PluginCall call, boolean ok, String eroare) {
        JSObject r = new JSObject();
        r.put("ok", ok);
        if (eroare != null) r.put("eroare", eroare);
        call.resolve(r);
    }

    /** Codurile BiometricPrompt → cuvinte stabile pentru punte: anulat | blocat | indisponibil | eroare (ca pe iPhone). */
    static String motivEroare(int cod) {
        switch (cod) {
            case BiometricPrompt.ERROR_USER_CANCELED:
            case BiometricPrompt.ERROR_NEGATIVE_BUTTON:
            case BiometricPrompt.ERROR_CANCELED:
                return "anulat";
            case BiometricPrompt.ERROR_LOCKOUT:
            case BiometricPrompt.ERROR_LOCKOUT_PERMANENT:
                return "blocat";
            case BiometricPrompt.ERROR_NO_BIOMETRICS:
            case BiometricPrompt.ERROR_HW_NOT_PRESENT:
            case BiometricPrompt.ERROR_HW_UNAVAILABLE:
            case BiometricPrompt.ERROR_NO_DEVICE_CREDENTIAL:
                return "indisponibil";
            default:
                return "eroare";
        }
    }

    // ——— Insigna ———

    /**
     * Pe Android insigna lansatorului urmează notificările afișate (nu există o cifră de pus direct, ca pe iPhone).
     * n = 0 ⇒ ștergem notificările afișate ale aplicației, în afară de cele în curs (meditația, AhMediaService).
     * n > 0 ⇒ nimic de făcut: cifra o dau notificările.
     */
    @PluginMethod
    public void insigna(PluginCall call) {
        int n = call.getInt("n", 0);
        if (n <= 0) {
            NotificationManager nm = (NotificationManager) getContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (nm != null) {
                try {
                    for (StatusBarNotification sb : nm.getActiveNotifications()) {
                        boolean inCurs = (sb.getNotification().flags & (Notification.FLAG_ONGOING_EVENT | Notification.FLAG_FOREGROUND_SERVICE)) != 0;
                        if (inCurs || sb.getId() == AhMediaService.ID_NOTIFICARE) continue;
                        nm.cancel(sb.getTag(), sb.getId());
                    }
                } catch (Exception e) {
                    Logger.warn("AhApp.insigna: " + e.getMessage());
                }
            }
        }
        JSObject r = new JSObject();
        r.put("ok", true);
        call.resolve(r);
    }

    // ——— Tema (ziua / seara) ———

    /**
     * Textul barelor de sistem: închis ziua, deschis seara (StatusBar.setStyle din JS ar face același lucru pentru bara
     * de sus); fondul din spatele paginii (vizibil cât se încarcă) după temă. Culoarea barelor se pune doar sub
     * Android 15: de acolo aplicația desenează sub bare (edge-to-edge) și le colorează pagina însăși.
     */
    @PluginMethod
    public void tema(PluginCall call) {
        final boolean seara = Boolean.TRUE.equals(call.getBoolean("seara", false));
        final AppCompatActivity a = getActivity();
        if (a == null) {
            JSObject r = new JSObject();
            r.put("ok", false);
            call.resolve(r);
            return;
        }
        a.runOnUiThread(() -> {
            Window w = a.getWindow();
            int fundal = seara ? FUNDAL_SEARA : FUNDAL_ZI;
            WindowInsetsControllerCompat ic = WindowCompat.getInsetsController(w, w.getDecorView());
            ic.setAppearanceLightStatusBars(!seara);
            ic.setAppearanceLightNavigationBars(!seara);
            if (Build.VERSION.SDK_INT < 35) {
                w.setStatusBarColor(fundal);
                w.setNavigationBarColor(fundal);
            }
            w.getDecorView().setBackgroundColor(fundal);
            WebView wv = getBridge() != null ? getBridge().getWebView() : null;
            if (wv != null) wv.setBackgroundColor(fundal);
            JSObject r = new JSObject();
            r.put("ok", true);
            call.resolve(r);
        });
    }
}
