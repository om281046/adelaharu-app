package ro.adelaharu.app;

import android.app.PendingIntent;
import android.appwidget.AppWidgetManager;
import android.appwidget.AppWidgetProvider;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.util.ArrayMap;
import android.util.SizeF;
import android.view.View;
import android.widget.RemoteViews;
import java.io.File;
import java.util.Map;

/**
 * Widgetul „Adela Haru” de pe ecranul principal: 2×2 (mic) și 4×2 (mediu), redimensionabil.
 * Arată ce i-a dat aplicația prin AhApp.widget (AhWidgetDate, în SharedPreferences „ah_widget”):
 * meditația zilei (titlu, text, poza descărcată de aplicație în files/widget/), faza ciclului (doar numele fazei și
 * textul dat) sau starea neutră (lotusul și „Adela Haru”). Widgetul nu descarcă nimic.
 * Atingerea deschide MainActivity cu ACTION_VIEW + adresa (ca un App Link) ⇒ App.addListener('appUrlOpen') din punte.
 */
public class AhWidget extends AppWidgetProvider {

    static final String PREFS = "ah_widget";
    /** de la această lățime (dp) se folosește aspectul mediu (4×2), cu poza */
    static final int LATIME_MEDIU = 220;

    @Override
    public void onUpdate(Context c, AppWidgetManager m, int[] ids) {
        deseneaza(c, m, ids);
    }

    @Override
    public void onAppWidgetOptionsChanged(Context c, AppWidgetManager m, int id, Bundle optiuni) {
        deseneaza(c, m, new int[] { id });
    }

    // ——— datele ———

    static void salveaza(Context c, AhWidgetDate d) {
        c.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString("mod", d.mod)
            .putString("titlu", d.titlu)
            .putString("sub", d.sub)
            .putString("culoare", d.culoare)
            .putString("url", d.url)
            .putBoolean("areImagine", d.areImagine)
            .putLong("actualizat", d.actualizat)
            .apply();
    }

    static AhWidgetDate citeste(Context c) {
        SharedPreferences p = c.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        AhWidgetDate d = AhWidgetDate.gol();
        d.mod = p.getString("mod", d.mod);
        d.titlu = p.getString("titlu", "");
        d.sub = p.getString("sub", "");
        d.culoare = AhWidgetDate.culoareSigura(p.getString("culoare", d.culoare));
        d.url = AhWidgetDate.adresaSigura(p.getString("url", d.url));
        d.areImagine = p.getBoolean("areImagine", false);
        d.actualizat = p.getLong("actualizat", 0);
        return d;
    }

    static File fisierImagine(Context c) {
        return new File(new File(c.getFilesDir(), "widget"), "imagine.jpg");
    }

    /** Redesenează toate widgeturile puse pe ecran (după AhApp.widget). */
    static void actualizeazaToate(Context c) {
        AppWidgetManager m = AppWidgetManager.getInstance(c);
        if (m == null) return;
        int[] ids = m.getAppWidgetIds(new ComponentName(c, AhWidget.class));
        if (ids != null && ids.length > 0) deseneaza(c, m, ids);
    }

    // ——— desenarea ———

    static void deseneaza(Context c, AppWidgetManager m, int[] ids) {
        AhWidgetDate d = citeste(c).deArata(System.currentTimeMillis());
        Bitmap poza = null;
        if ("meditatie".equals(d.mod) && d.areImagine) {
            File f = fisierImagine(c);
            if (f.exists()) poza = BitmapFactory.decodeFile(f.getAbsolutePath());
        }
        for (int id : ids) {
            RemoteViews v;
            if ("nimic".equals(d.mod)) {
                v = vedere(c, d, null, R.layout.ah_widget_neutru);
            } else if (Build.VERSION.SDK_INT >= 31) {
                // Android 12+: lansatorul alege singur aspectul după mărimea reală (și la redimensionare)
                Map<SizeF, RemoteViews> dupaMarime = new ArrayMap<>();
                dupaMarime.put(new SizeF(110f, 110f), vedere(c, d, null, R.layout.ah_widget_mic));
                dupaMarime.put(new SizeF(LATIME_MEDIU, 110f), vedere(c, d, poza, R.layout.ah_widget_mediu));
                v = new RemoteViews(dupaMarime);
            } else {
                Bundle o = m.getAppWidgetOptions(id);
                int latime = o == null ? 0 : o.getInt(AppWidgetManager.OPTION_APPWIDGET_MIN_WIDTH, 0);
                v = latime >= LATIME_MEDIU ? vedere(c, d, poza, R.layout.ah_widget_mediu) : vedere(c, d, null, R.layout.ah_widget_mic);
            }
            m.updateAppWidget(id, v);
        }
    }

    static RemoteViews vedere(Context c, AhWidgetDate d, Bitmap poza, int aspect) {
        RemoteViews v = new RemoteViews(c.getPackageName(), aspect);
        v.setInt(R.id.ah_w_semn, "setColorFilter", AhWidgetDate.culoareArgb(d.culoare));
        if (aspect != R.layout.ah_widget_neutru) {
            v.setTextViewText(R.id.ah_w_titlu, d.titlu);
            v.setViewVisibility(R.id.ah_w_titlu, d.titlu.isEmpty() ? View.GONE : View.VISIBLE);
            v.setTextViewText(R.id.ah_w_sub, d.sub);
            v.setViewVisibility(R.id.ah_w_sub, d.sub.isEmpty() ? View.GONE : View.VISIBLE);
        }
        if (aspect == R.layout.ah_widget_mediu) {
            if (poza != null) {
                v.setImageViewBitmap(R.id.ah_w_imagine, poza);
                v.setViewVisibility(R.id.ah_w_imagine, View.VISIBLE);
            } else {
                v.setViewVisibility(R.id.ah_w_imagine, View.GONE);
            }
        }
        v.setOnClickPendingIntent(R.id.ah_w_radacina, atingere(c, d.url));
        return v;
    }

    /** ACTION_VIEW explicit spre MainActivity (singleTask ⇒ onNewIntent ⇒ appUrlOpen; la pornirea rece, getLaunchUrl). */
    static PendingIntent atingere(Context c, String url) {
        Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse(AhWidgetDate.adresaSigura(url)));
        i.setClass(c, MainActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        return PendingIntent.getActivity(c, 7301, i, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }
}
