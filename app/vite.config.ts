import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';

/** Politica de securitate a conținutului, doar în build (în dev, Vite are nevoie de scripturi inline). */
const CSP = [
  "default-src 'self' capacitor: https://localhost http://localhost",
  "script-src 'self'",
  "style-src 'self' 'unsafe-inline'",
  'img-src \'self\' data: blob: https: capacitor:',
  'media-src \'self\' blob: data: https: capacitor: http://localhost https://localhost',
  'frame-src https://www.adelaharu.ro https://player.vimeo.com data:',
  'connect-src \'self\' https://www.adelaharu.ro blob: data: capacitor: http://localhost https://localhost',
  "font-src 'self' data:",
].join('; ');

const csp = (): Plugin => ({
  name: 'ah-csp',
  apply: 'build',
  transformIndexHtml: (html) => html.replace('<meta charset="UTF-8" />', `<meta charset="UTF-8" />\n    <meta http-equiv="Content-Security-Policy" content="${CSP}" />`),
});

export default defineConfig({
  plugins: [react(), csp()],
  base: './',
  build: { target: 'es2020', sourcemap: false },
  test: { environment: 'node', include: ['src/**/*.test.ts'] },
} as never);
