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
 * - allowNavigation: doar aceste gazde rămân în aplicație. Orice altă adresă (Spotify, Apple Podcasts,
 *   Zoom, WhatsApp, mailto:, tel:) o deschide Capacitor singur în browserul / aplicația sistemului
 *   (iOS: WebViewDelegationHandler → UIApplication.open; Android: Bridge.launchIntent → ACTION_VIEW).
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
    allowNavigation: ['www.adelaharu.ro', 'adelaharu.ro', 'player.vimeo.com', '*.vimeocdn.com', 'embed.podcasts.apple.com'],
    cleartext: false,
  },
  ios: {
    appendUserAgent: ua('ios'),
    backgroundColor: FUNDAL,
    // Spațiul meu nu folosește viewport-fit=cover: WKWebView lasă singur loc pentru bara de sus.
    // Dacă fragmentul adaugă viewport-fit=cover în aplicație, se trece pe 'never' (vezi README).
    contentInset: 'automatic',
    allowsLinkPreview: false,
    limitsNavigationsToAppBoundDomains: false,
  },
  android: {
    appendUserAgent: ua('android'),
    backgroundColor: FUNDAL,
    allowMixedContent: false,
    captureInput: false,
  },
  plugins: {
    // Site-ul își face singur cererile (cookie-ul ah_sp): fără interceptarea fetch/XHR și a cookie-urilor.
    CapacitorHttp: { enabled: false },
    CapacitorCookies: { enabled: false },
    SystemBars: { insetsHandling: 'css' },
    SplashScreen: {
      // se ascunde singur; puntea de pe site sau pagina offline îl pot ascunde mai devreme (SplashScreen.hide())
      launchAutoHide: true,
      launchShowDuration: 1500,
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
    LocalNotifications: { smallIcon: 'ic_stat_lotus', iconColor: '#964E46' },
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
  },
};

export default config;
