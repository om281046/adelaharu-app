import { afterEach, describe, expect, it, vi } from 'vitest';
import { laInapoi } from './cap';

/** window.Capacitor imitat: App cu addListener / minimizeApp. */
function capacitor(platforma: string) {
  const l: Record<string, (d: unknown) => void> = {};
  const minimizeApp = vi.fn(() => Promise.resolve());
  (globalThis as unknown as { window: unknown }).window = {
    Capacitor: {
      getPlatform: () => platforma,
      Plugins: { App: { addListener: (ev: string, cb: (d: unknown) => void) => { l[ev] = cb; return Promise.resolve({ remove() {} }); }, minimizeApp } },
    },
  };
  return { l, minimizeApp };
}

afterEach(() => {
  delete (globalThis as unknown as { window?: unknown }).window;
});

describe('butonul Înapoi pe pagina offline (Android)', () => {
  it('închide întâi ce e deschis, apoi trece aplicația în fundal (fără history.back)', async () => {
    const { l, minimizeApp } = capacitor('android');
    let deschis = true;
    await laInapoi(() => {
      if (deschis) {
        deschis = false;
        return true;
      }
      return false;
    });
    expect(typeof l.backButton).toBe('function');
    l.backButton({ canGoBack: true });
    expect(minimizeApp).not.toHaveBeenCalled();
    l.backButton({ canGoBack: true });
    expect(minimizeApp).toHaveBeenCalledTimes(1);
  });
  it('pe iPhone nu se leagă nimic', async () => {
    const { l } = capacitor('ios');
    await laInapoi(() => false);
    expect(l.backButton).toBeUndefined();
  });
});
