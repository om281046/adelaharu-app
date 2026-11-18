import { createHash } from 'node:crypto';
import { copyFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite';

/**
 * Pagina offline e UN SINGUR fișier HTML, cu JS, CSS și fonturi incluse:
 * pe Android, Capacitor servește local doar adresa exactă din server.errorPath
 * (https://www.adelaharu.ro/offline.html); orice ./assets/… ar fi cerut de la site și ar lipsi offline.
 */
const unSingurFisier = (): Plugin => ({
  name: 'ah-un-singur-fisier',
  apply: 'build',
  enforce: 'post',
  generateBundle(_o, bundle) {
    const html = Object.values(bundle).find((f) => f.type === 'asset' && f.fileName.endsWith('.html'));
    if (!html || html.type !== 'asset') return;
    let s = String(html.source);
    const hashuri: string[] = [];
    for (const [nume, f] of Object.entries(bundle)) {
      if (f.type === 'chunk' && f.isEntry) {
        const cod = f.code.replace(/<\/script/gi, '<\\/script');
        hashuri.push(`'sha256-${createHash('sha256').update(cod).digest('base64')}'`);
        s = s.replace(new RegExp(`<script[^>]*src="[^"]*${nume.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"[^>]*></script>`), () => `<script type="module">${cod}</script>`);
        delete bundle[nume];
      } else if (f.type === 'asset' && f.fileName.endsWith('.css')) {
        s = s.replace(new RegExp(`<link[^>]*href="[^"]*${nume.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"[^>]*>`), () => `<style>${String(f.source)}</style>`);
        delete bundle[nume];
      }
    }
    // CSP: doar scriptul nostru (prin hash); sunetul și pozele descărcate vin de la serverul local Capacitor
    const csp = [
      "default-src 'self'",
      `script-src ${hashuri.join(' ')}`,
      "style-src 'unsafe-inline'",
      "img-src 'self' data: blob: capacitor: https:",
      "media-src 'self' blob: capacitor: https:",
      "connect-src 'self' https://www.adelaharu.ro",
      'font-src data:',
      "base-uri 'none'",
      "form-action 'none'",
    ].join('; ');
    s = s.replace('<meta charset="UTF-8" />', `<meta charset="UTF-8" />\n    <meta http-equiv="Content-Security-Policy" content="${csp}" />`);
    html.source = s;
  },
  closeBundle() {
    // index.html = aceeași pagină (Capacitor cere un index.html în webDir; nu se deschide cât timp există server.url)
    copyFileSync(resolve(import.meta.dirname, 'dist/offline.html'), resolve(import.meta.dirname, 'dist/index.html'));
  },
});

export default defineConfig({
  plugins: [unSingurFisier()],
  base: './',
  build: {
    target: ['es2020', 'safari15', 'chrome90'],
    sourcemap: false,
    assetsInlineLimit: 1e8,
    cssCodeSplit: false,
    modulePreload: false,
    rollupOptions: { input: { offline: resolve(import.meta.dirname, 'offline.html') } },
  },
  test: { environment: 'node', include: ['src/**/*.test.ts'] },
} as never);
