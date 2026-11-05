/** Generează assets/icon-only.png, icon-foreground.png, icon-background.png, splash*.png și public/icon-512.png (lotusul pe piersică). */
const { chromium } = require('playwright');
const path = require('node:path');
const LOTUS = (c, w) => `<g fill="none" stroke="${c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20c-4.5 0-8-2.4-9-6 2.6-.4 5.6.4 9 6Zm0 0c4.5 0 8-2.4 9-6-2.6-.4-5.6.4-9 6Z"/><path d="M12 20c-2.2-2.6-3-5.6-2.4-9C11 12 12 13.8 12 20Zm0 0c2.2-2.6 3-5.6 2.4-9C13 12 12 13.8 12 20Z"/><path d="M12 15.5c-1.4-3-1.3-6.7 0-11 1.3 4.3 1.4 8 0 11Z"/></g>`;
const svg = (m, bg, scala, text) => `<svg xmlns="http://www.w3.org/2000/svg" width="${m}" height="${m}" viewBox="0 0 ${m} ${m}">${bg ? `<rect width="${m}" height="${m}" fill="${bg}"/>` : ''}<g transform="translate(${m / 2 - 12 * scala} ${m / 2 - 13 * scala - (text ? m * 0.05 : 0)}) scale(${scala})">${LOTUS('#A8573A', 1.1)}</g>${text ? `<text x="${m / 2}" y="${m / 2 + 14 * scala}" text-anchor="middle" font-family="Georgia, serif" font-size="${m * 0.045}" fill="#7A4A38" letter-spacing="2">Adela Haru</text>` : ''}</svg>`;
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage();
  const fac = async (fis, m, s) => {
    await p.setViewportSize({ width: m, height: m });
    await p.setContent(`<body style="margin:0;background:transparent">${s}</body>`);
    await p.screenshot({ path: path.resolve(__dirname, '..', fis), omitBackground: true, clip: { x: 0, y: 0, width: m, height: m } });
  };
  await fac('assets/icon-only.png', 1024, svg(1024, '#FBE6D9', 28));
  await fac('assets/icon-foreground.png', 1024, svg(1024, null, 20));
  await fac('assets/icon-background.png', 1024, svg(1024, '#FBE6D9', 0.0001));
  await fac('assets/splash.png', 2732, svg(2732, '#FFF7F0', 30, true));
  await fac('assets/splash-dark.png', 2732, svg(2732, '#1F1814', 30, true));
  await fac('public/icon-512.png', 512, svg(512, '#FBE6D9', 14));
  await b.close();
})();
