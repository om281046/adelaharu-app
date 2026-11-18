package ro.adelaharu.app;

import android.os.Bundle;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
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
 */
public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(AhAppPlugin.class);
        registerPlugin(AhMediaPlugin.class);
        super.onCreate(savedInstanceState);
        if (bridge != null) {
            bridge.setWebViewClient(new AhWebViewClient(bridge, this));
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
