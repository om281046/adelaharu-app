package ro.adelaharu.app;

import android.content.Intent;
import androidx.core.content.ContextCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Comenzile de pe ecranul blocat și din notificare pentru sunetul care cântă în WebView.
 * WebView-ul Android nu leagă navigator.mediaSession de sistem și nu are serviciu de prim-plan,
 * așa că fără acest plugin Android poate opri aplicația în fundal în timpul unei meditații.
 *
 * JS (pagina offline sau puntea de pe site):
 *   Capacitor.Plugins.AhMedia.actualizeaza({titlu, artist, poza, durata, pozitie, canta})
 *   Capacitor.Plugins.AhMedia.opreste()
 *   Capacitor.Plugins.AhMedia.addListener('actiune', ({tip, pozitie}) => …)
 *     tip: play | pause | inainte | inapoi | seek (cu pozitie, secunde) | stop
 * Sunetul rămâne în <audio> din pagină; pluginul doar arată starea și trimite înapoi apăsările.
 */
@CapacitorPlugin(name = "AhMedia")
public class AhMediaPlugin extends Plugin {

    private static AhMediaPlugin instanta;

    @Override
    public void load() {
        instanta = this;
    }

    @Override
    protected void handleOnDestroy() {
        if (instanta == this) instanta = null;
        AhMediaService s = AhMediaService.activ;
        if (s != null) s.opreste();
    }

    /** Apelat de serviciu când cineva apasă pe ecranul blocat, în notificare sau pe căști. */
    static void trimite(String tip, Double pozitie) {
        AhMediaPlugin p = instanta;
        if (p == null) return;
        JSObject d = new JSObject();
        d.put("tip", tip);
        if (pozitie != null) d.put("pozitie", pozitie);
        p.notifyListeners("actiune", d);
    }

    @PluginMethod
    public void actualizeaza(PluginCall call) {
        AhMediaService.Stare s = new AhMediaService.Stare();
        s.titlu = call.getString("titlu", "");
        s.artist = call.getString("artist", "Adela Haru");
        s.poza = call.getString("poza", null);
        s.durata = call.getDouble("durata", 0.0);
        s.pozitie = call.getDouble("pozitie", 0.0);
        s.canta = Boolean.TRUE.equals(call.getBoolean("canta", false));

        AhMediaService activ = AhMediaService.activ;
        if (activ != null) {
            activ.actualizeaza(s);
            call.resolve();
            return;
        }
        if (!s.canta) {
            // nimic de arătat până nu pornește sunetul
            call.resolve();
            return;
        }
        // serviciul pornește doar la o apăsare pe Play, cu aplicația deschisă (regula Android 12+)
        AhMediaService.deAplicat = s;
        try {
            ContextCompat.startForegroundService(getContext(), new Intent(getContext(), AhMediaService.class));
            call.resolve();
        } catch (Exception e) {
            AhMediaService.deAplicat = null;
            call.reject("Nu am putut porni serviciul audio: " + e.getMessage());
        }
    }

    @PluginMethod
    public void opreste(PluginCall call) {
        AhMediaService s = AhMediaService.activ;
        if (s != null) s.opreste();
        call.resolve();
    }
}
