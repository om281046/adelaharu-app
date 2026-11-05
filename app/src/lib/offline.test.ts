import { describe, expect, it, vi } from 'vitest';
vi.mock('@capacitor/filesystem', () => ({ Directory: { LibraryNoCloud: 'LIBRARY_NO_CLOUD' }, Filesystem: {} }));
vi.mock('@capacitor/file-transfer', () => ({ FileTransfer: {} }));
vi.mock('../api', () => ({ api: {} }));
const { extensieAudio } = await import('./offline');

describe('extensia fișierului descărcat', () => {
  it('după Content-Type', () => {
    expect(extensieAudio('audio/mpeg', 'https://www.adelaharu.ro/?ah_app_media=1')).toBe('mp3');
    expect(extensieAudio('audio/mp4; charset=binary', 'https://x/?a=1')).toBe('m4a');
    expect(extensieAudio('audio/x-m4a', 'https://x/')).toBe('m4a');
  });
  it('după adresă sau implicit mp3', () => {
    expect(extensieAudio(null, 'https://x/f/med.m4a?sig=1')).toBe('m4a');
    expect(extensieAudio('application/octet-stream', 'https://www.adelaharu.ro/?ah_app_media=1')).toBe('mp3');
  });
});
