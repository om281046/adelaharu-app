package ro.adelaharu.app;

import java.net.URI;
import java.util.Locale;

/**
 * Datele widgetului de pe ecranul principal (AhWidget), scrise de AhApp.widget din puntea site-ului.
 * Doar Java simplu (fără clase Android), ca regulile să poată fi verificate cu JUnit (src/test/…/AhWidgetDateTest).
 * Aceleași reguli ca pe iPhone (ios/App/AdelaHaruWidget/AhWidgetDate.swift).
 *
 * Confidențialitate: la mod = "faza" se păstrează DOAR numele fazei (titlu) și textul dat (sub), fără poză și fără
 * altceva; la mod = "nimic" nu se păstrează niciun text (widgetul arată doar lotusul și „Adela Haru”).
 */
final class AhWidgetDate {

    static final String ACCENT = "#964E46";                      // accentul Spațiului meu (sn235)
    static final String ACASA = "https://www.adelaharu.ro/spatiul-meu/";
    /** „Meditația zilei” și faza sunt ale unei zile: după 36 h fără o deschidere a aplicației, starea neutră. */
    static final long VALABIL_MS = 36L * 3600 * 1000;
    static final int MAX_TITLU = 80;
    static final int MAX_SUB = 140;

    String mod = "nimic";     // meditatie | faza | nimic
    String titlu = "";
    String sub = "";
    String culoare = ACCENT;  // #RRGGBB
    String url = ACASA;
    boolean areImagine = false;
    long actualizat = 0;      // ms de la 1970

    static AhWidgetDate gol() {
        return new AhWidgetDate();
    }

    /** Curăță ce vine din pagină (funcție pură). */
    static AhWidgetDate curata(String mod, String titlu, String sub, String culoare, String url, long acum) {
        AhWidgetDate d = new AhWidgetDate();
        d.mod = "meditatie".equals(mod) || "faza".equals(mod) ? mod : "nimic";
        d.culoare = culoareSigura(culoare);
        d.url = adresaSigura(url);
        d.actualizat = acum;
        if (!"nimic".equals(d.mod)) {
            d.titlu = scurteaza(titlu, MAX_TITLU);
            d.sub = scurteaza(sub, MAX_SUB);
            if (d.titlu.isEmpty() && d.sub.isEmpty()) d.mod = "nimic";
        }
        return d;
    }

    /** Starea de arătat acum: datele vechi (sau un mod necunoscut) devin starea neutră, cu aceeași culoare. */
    AhWidgetDate deArata(long acum) {
        if ("nimic".equals(mod)) return this;
        if (!"meditatie".equals(mod) && !"faza".equals(mod)) return gol();
        if (actualizat <= 0 || acum - actualizat > VALABIL_MS) {
            AhWidgetDate g = gol();
            g.culoare = culoare;
            return g;
        }
        return this;
    }

    static String scurteaza(String s, int n) {
        if (s == null) return "";
        String t = s.replaceAll("[\\r\\n]+", " ").trim();
        int puncte = t.codePointCount(0, t.length());
        if (puncte <= n) return t;
        int capat = t.offsetByCodePoints(0, n - 1);
        return t.substring(0, capat).trim() + "…";
    }

    /** Doar "#RGB" / "#RRGGBB"; altfel accentul. */
    static String culoareSigura(String s) {
        if (s == null) return ACCENT;
        String t = s.trim();
        if (!t.startsWith("#")) return ACCENT;
        String hex = t.substring(1);
        if (!hex.matches("[0-9a-fA-F]{3}|[0-9a-fA-F]{6}")) return ACCENT;
        if (hex.length() == 3) {
            StringBuilder b = new StringBuilder();
            for (char c : hex.toCharArray()) b.append(c).append(c);
            hex = b.toString();
        }
        return "#" + hex.toUpperCase(Locale.ROOT);
    }

    /** "#RRGGBB" → culoare ARGB opacă (pentru RemoteViews). */
    static int culoareArgb(String s) {
        return 0xFF000000 | Integer.parseInt(culoareSigura(s).substring(1), 16);
    }

    /** Atingerea deschide doar o pagină a site-ului (App Link /spatiul-meu/#…); orice altceva → Spațiul meu. */
    static String adresaSigura(String s) {
        if (s == null) return ACASA;
        try {
            URI u = new URI(s.trim());
            String gazda = u.getHost() == null ? "" : u.getHost().toLowerCase(Locale.ROOT);
            if (!"https".equalsIgnoreCase(u.getScheme())) return ACASA;
            if (!gazda.equals("www.adelaharu.ro") && !gazda.equals("adelaharu.ro")) return ACASA;
            if (u.getRawUserInfo() != null || u.getPort() != -1) return ACASA;
            return u.toString();
        } catch (Exception e) {
            return ACASA;
        }
    }
}
