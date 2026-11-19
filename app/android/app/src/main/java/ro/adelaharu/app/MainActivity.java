package ro.adelaharu.app;

import android.content.pm.ActivityInfo;
import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import android.widget.FrameLayout;
import androidx.activity.OnBackPressedCallback;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebChromeClient;
import com.getcapacitor.BridgeWebViewClient;
import com.getcapacitor.Logger;

/**
 * Aplicația = Spațiul meu (server.url) + pluginurile din npm (se înregistrează singure)
 * + două pluginuri mici ale noastre: AhApp (ce poate telefonul) și AhMedia (ecranul blocat).
 *
 * 2.3.0: clientul WebView al lui Capacitor, cu două lucruri în plus (AhWebViewClient):
 *  - o eroare HTTP a paginii principale (Capacitor deschide atunci pagina offline, fără să spună de ce) e ținută minte
 *    în AhAppPlugin (cod + moment), iar pagina offline o citește prin AhApp.info().eroare și scrie „Site-ul e în lucru”
 *    în loc de „Nu avem internet” (adresa paginii offline nu poate primi ?cod=: serverul local o recunoaște doar exact);
 *  - procesul care desenează pagina (renderer) oprit de sistem (memorie puțină, WebView actualizat): fără tratare,
 *    Android închide toată aplicația. Întoarcem true și refacem activitatea (un WebView nou, Spațiul meu de la capăt).
 *
 * Video pe tot ecranul (AhWebChromeClient): BridgeWebChromeClient din Capacitor 8 refuză ecranul complet
 * (onShowCustomView cheamă imediat callback.onCustomViewHidden()), deci butonul „ecran complet” din playerul Vimeo nu
 * făcea nimic. Aici vederea videoului se pune peste tot ecranul, barele de sistem se ascund, ecranul rămâne aprins și
 * orientarea trece pe peisaj (după senzor) cât ține; Înapoi închide întâi ecranul complet. Restul (fișiere, permisiuni
 * pentru cameră/microfon, consola) rămâne al lui Capacitor (extindem clasa lui).
 */
public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(AhAppPlugin.class);
        registerPlugin(AhMediaPlugin.class);
        super.onCreate(savedInstanceState);
        if (bridge != null) {
            bridge.setWebViewClient(new AhWebViewClient(bridge, this));
            // în onCreate: BridgeWebChromeClient își înregistrează lansatoarele de rezultat (registerForActivityResult),
            // permis doar înainte de onStart
            AhWebChromeClient chrome = new AhWebChromeClient(bridge, this);
            bridge.getWebView().setWebChromeClient(chrome);
            // după super.onCreate ⇒ după callback-ul pluginului App (backButton), deci are prioritate cât e activ
            getOnBackPressedDispatcher().addCallback(this, chrome.inapoi);
        }
    }

    static class AhWebChromeClient extends BridgeWebChromeClient {

        private final BridgeActivity activitate;
        private View vedere;
        private CustomViewCallback inchide;
        private int orientareaDinainte = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED;

        /** Înapoi, cât e un video pe tot ecranul: iese din ecranul complet (nu navighează în pagină). */
        final OnBackPressedCallback inapoi = new OnBackPressedCallback(false) {
            @Override
            public void handleOnBackPressed() {
                onHideCustomView();
            }
        };

        AhWebChromeClient(Bridge bridge, BridgeActivity activitate) {
            super(bridge);
            this.activitate = activitate;
        }

        @Override
        public void onShowCustomView(View view, CustomViewCallback callback) {
            if (vedere != null) {
                // deja pe tot ecranul: WebView-ul cere închiderea celei noi
                callback.onCustomViewHidden();
                return;
            }
            Window w = activitate.getWindow();
            ViewGroup decor = (ViewGroup) w.getDecorView();
            vedere = view;
            inchide = callback;
            view.setBackgroundColor(Color.BLACK);
            decor.addView(view, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
            WindowInsetsControllerCompat ic = WindowCompat.getInsetsController(w, decor);
            ic.setSystemBarsBehavior(WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
            ic.hide(WindowInsetsCompat.Type.systemBars());
            w.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            orientareaDinainte = activitate.getRequestedOrientation();
            // filmele sălii de curs sunt orizontale: peisaj după senzor (ambele sensuri), chiar cu rotirea blocată
            activitate.setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
            inapoi.setEnabled(true);
        }

        @Override
        public void onHideCustomView() {
            if (vedere == null) return;
            Window w = activitate.getWindow();
            ViewGroup decor = (ViewGroup) w.getDecorView();
            decor.removeView(vedere);
            vedere = null;
            inapoi.setEnabled(false);
            WindowCompat.getInsetsController(w, decor).show(WindowInsetsCompat.Type.systemBars());
            w.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            activitate.setRequestedOrientation(orientareaDinainte);
            CustomViewCallback c = inchide;
            inchide = null;
            // spune WebView-ului că ecranul complet s-a închis (altfel playerul rămâne „în ecran complet”)
            if (c != null) c.onCustomViewHidden();
        }
    }

    static class AhWebViewClient extends BridgeWebViewClient {

        private final BridgeActivity activitate;
        private static long primaOprire = 0;
        private static int opriri = 0;

        AhWebViewClient(Bridge bridge, BridgeActivity activitate) {
            super(bridge);
            this.activitate = activitate;
        }

        @Override
        public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
            if (request != null && request.isForMainFrame() && errorResponse != null) {
                AhAppPlugin.noteazaEroare(errorResponse.getStatusCode());
            }
            super.onReceivedHttpError(view, request, errorResponse);
        }

        @Override
        public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
            super.onRenderProcessGone(view, detail);
            boolean cazut = android.os.Build.VERSION.SDK_INT >= 26 && detail != null && detail.didCrash();
            Logger.error("AhWebViewClient: procesul WebView s-a oprit" + (cazut ? " (căzut)" : " (oprit de sistem)") + "; refac ecranul");
            try {
                // scos din ecran (nu se mai desenează); la refacere, BridgeActivity.onDetachedFromWindow → Bridge îl închide (destroy)
                if (view.getParent() instanceof android.view.ViewGroup) {
                    ((android.view.ViewGroup) view.getParent()).removeView(view);
                }
            } catch (Exception e) {
                Logger.error("AhWebViewClient: WebView-ul vechi nu s-a putut închide", e);
            }
            // fără buclă: a treia oprire într-un minut închide ecranul (aplicația se redeschide curat de la pictogramă)
            long acum = System.currentTimeMillis();
            if (acum - primaOprire > 60000) {
                primaOprire = acum;
                opriri = 0;
            }
            opriri++;
            activitate.runOnUiThread(opriri >= 3 ? activitate::finish : activitate::recreate);
            return true;
        }
    }
}
