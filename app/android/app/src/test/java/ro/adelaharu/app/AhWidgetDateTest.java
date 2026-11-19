package ro.adelaharu.app;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;

import org.junit.Test;

/** Regulile datelor widgetului (aceleași ca AhWidgetDate.swift pe iPhone). */
public class AhWidgetDateTest {

    @Test
    public void culoarea() {
        assertEquals("#AABBCC", AhWidgetDate.culoareSigura("#abc"));
        assertEquals("#964E46", AhWidgetDate.culoareSigura("red"));
        assertEquals("#964E46", AhWidgetDate.culoareSigura("#12345g"));
        assertEquals(0xFFFBF6F3, AhWidgetDate.culoareArgb("#FBF6F3"));
    }

    @Test
    public void adresaDoarSpreSite() {
        assertEquals("https://www.adelaharu.ro/spatiul-meu/#ciclu", AhWidgetDate.adresaSigura("https://www.adelaharu.ro/spatiul-meu/#ciclu"));
        assertEquals(AhWidgetDate.ACASA, AhWidgetDate.adresaSigura("https://altcineva.ro/x"));
        assertEquals(AhWidgetDate.ACASA, AhWidgetDate.adresaSigura("http://www.adelaharu.ro/"));
        assertEquals(AhWidgetDate.ACASA, AhWidgetDate.adresaSigura("https://a@www.adelaharu.ro/"));
        assertEquals(AhWidgetDate.ACASA, AhWidgetDate.adresaSigura("javascript:alert(1)"));
        assertEquals(AhWidgetDate.ACASA, AhWidgetDate.adresaSigura(null));
    }

    @Test
    public void fazaPastreazaDoarTextul() {
        AhWidgetDate d = AhWidgetDate.curata("faza", "Ovulația", "Energie\nmare", "#fff", "https://www.adelaharu.ro/spatiul-meu/#ciclu", 1000);
        assertEquals("faza", d.mod);
        assertEquals("Ovulația", d.titlu);
        assertEquals("Energie mare", d.sub);
        assertFalse(d.areImagine);
    }

    @Test
    public void nimicFaraText() {
        assertEquals("nimic", AhWidgetDate.curata("altceva", "a", "b", null, null, 1).mod);
        assertEquals("", AhWidgetDate.curata("nimic", "a", "b", null, null, 1).titlu);
        assertEquals("nimic", AhWidgetDate.curata("faza", " ", "", null, null, 1).mod);
    }

    @Test
    public void expiraDupa36Ore() {
        AhWidgetDate d = AhWidgetDate.curata("meditatie", "Titlu", "", null, null, 1000);
        assertEquals("meditatie", d.deArata(2000).mod);
        assertEquals("nimic", d.deArata(1000 + AhWidgetDate.VALABIL_MS + 1).mod);
    }

    @Test
    public void scurteaza() {
        StringBuilder b = new StringBuilder();
        for (int i = 0; i < 100; i++) b.append("ă");
        String s = AhWidgetDate.scurteaza(b.toString(), 80);
        assertEquals(80, s.codePointCount(0, s.length()));
        assertEquals("…", s.substring(s.length() - 1));
    }
}
