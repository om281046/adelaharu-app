import { readFileSync } from 'node:fs';
import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Aplicația v2 = Spațiul meu de pe site + funcții de telefon (vezi ../docs/arhitectura.md).
 *
 * - server.url: WebView-ul deschide direct pagina contului; ce adaugă echipa pe site apare singur.
 * - server.errorPath: pagina locală offline.html (un singur fișier, din `npm run build`), când site-ul nu răspunde.
 * - server.hostname: gazda paginilor locale. Pe Android pagina offline devine https://www.adelaharu.ro/offline.html
 *   (servită tot local, Bridge.getErrorUrl + WebViewLocalServer.isErrorUrl), deci pe aceeași origine cu server.url:
 *   doar așa primește puntea Capacitor (addDocumentStartJavaScript se injectează numai pe originea lui server.url)
 *   și poate citi Preferences / Filesystem / AhMedia. Cu „localhost” (implicit) pagina offline n-ar avea window.Capacitor.
 *   Pe iOS schimbă doar gazda schemei locale: capacitor://www.adelaharu.ro.
 * - allowNavigation: doar site-ul (www.adelaharu.ro, adelaharu.ro) rămâne în aplicație. Orice altă adresă de nivel
 *   superior (Spotify, Apple Podcasts, Zoom, WhatsApp, mailto:, tel:) o deschide Capacitor singur în browserul / aplicația
 *   sistemului (iOS: WebViewDelegationHandler → UIApplication.open; Android: Bridge.launchIntent → ACTION_VIEW).
 *   iframe-urile (Vimeo) nu sunt navigări de nivel superior: se încarcă și fără să fie aici. Lista contează și pentru
 *   siguranță: pe Android, originile din allowNavigation primesc puntea (WebViewCompat.addWebMessageListener, doar
 *   cadrul principal); pe iOS, AhBridgeViewController lasă puntea doar paginii principale de pe https://www.adelaharu.ro.
 * - appendUserAgent: după el recunoaște fragmentul ah-sp-aplicatie.php aplicația (ah_spa_e_aplicatie()).
 */
// versiunea din package.json (aceeași cu versionName / MARKETING_VERSION: vezi README, „Versiuni”)
const versiune: string = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8')).version;
const ua = (p: 'ios' | 'android') => `AdelaHaruApp/${versiune} (${p})`;

// paleta Spațiului meu (sn235): fundal #FBF6F3, accent #964E46
const FUNDAL = '#FBF6F3';

const config: CapacitorConfig = {
  appId: 'ro.adelaharu.app',
  appName: 'Adela Haru',
  webDir: 'dist',
  backgroundColor: FUNDAL,
  loggingBehavior: 'debug',
  server: {
    url: 'https://www.adelaharu.ro/spatiul-meu/',
    errorPath: 'offline.html',
    hostname: 'www.adelaharu.ro',
    allowNavigation: ['www.adelaharu.ro', 'adelaharu.ro'],
    cleartext: false,
  },
  ios: {
    appendUserAgent: ua('ios'),
    backgroundColor: FUNDAL,
    // Un singur model pentru zonele sigure: pagina desenează până în margini (fragmentul pune viewport-fit=cover în
    // aplicație; pagina offline îl are), iar antetul și barele țin loc cu env(safe-area-inset-*). Fără inset de la WebView.
    contentInset: 'never',
    allowsLinkPreview: false,
    limitsNavigationsToAppBoundDomains: false,
  },
  android: {
    appendUserAgent: ua('android'),
    backgroundColor: FUNDAL,
    allowMixedContent: false,
    captureInput: false,
    // la fel ca ținta din vite.config.ts (chrome90). Sub ea, Capacitor (Bridge.isMinimumWebViewInstalled) nu deschide
    // site-ul: încarcă direct pagina offline, care vede versiunea din User-Agent și cere actualizarea „Android System
    // WebView” / „Chrome” (legatura.ts, motivul „webview”). Android 7–9 fără actualizări din Magazin Play rămân acolo.
    minWebViewVersion: 90,
  },
  plugins: {
    // Site-ul își face singur cererile (cookie-ul ah_sp): fără interceptarea fetch/XHR și a cookie-urilor.
    CapacitorHttp: { enabled: false },
    CapacitorCookies: { enabled: false },
    SystemBars: { insetsHandling: 'css' },
    SplashScreen: {
      // rămâne până se vede pagina: puntea de pe site (după prima desenare) sau pagina offline îl ascund cu
      // SplashScreen.hide(); dacă nu (pagina lui, o eroare), se ascunde singur după 4 s
      launchAutoHide: true,
      launchShowDuration: 4000,
      launchFadeOutDuration: 300,
      backgroundColor: FUNDAL,
      showSpinner: false,
      androidScaleType: 'CENTER_CROP',
      splashFullScreen: false,
      splashImmersive: false,
    },
    StatusBar: {
      overlaysWebView: false,
      style: 'LIGHT',
      backgroundColor: FUNDAL,
    },
    // pictograma albă din res/drawable (ca a notificărilor push: meta-data default_notification_icon în AndroidManifest)
    LocalNotifications: { smallIcon: 'ic_stat_lotus', iconColor: '#964E46' },
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
  },
};

export default config;
