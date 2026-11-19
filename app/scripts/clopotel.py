#!/usr/bin/env python3
"""
Clopoțelul notificărilor (sunet ORIGINAL, sintetizat aici, fără înregistrări străine).

Un bol tibetan simplificat: doi parțiali inarmonici (528 Hz și 1320 Hz, raport 2,5) plus două umbre slabe
(1,5 Hz depărtare: „bătaia” lentă a unui bol real), atac moale de 12 ms, stingere exponențială lentă,
2,5 s, vârf la −3 dBFS, mono 44,1 kHz, 16 biți.

Scrie:
  android/app/src/main/res/raw/clopotel.wav   (Android: canalele ah_* cu sound „clopotel.wav”)
  ios/App/App/clopotel.caf                     (iOS: sound „clopotel.caf”, în resursele țintei App)

Folosire (din app/):  python3 scripts/clopotel.py            # cere numpy; .caf prin ffmpeg (sau afconvert pe macOS)
Înlocuirea cu alt sunet (de ex. un clopoțel înregistrat de Adela): vezi app/README.md, „Sunetele notificărilor”.
Limite: Android < 300 KB (ținem mult sub), iOS < 30 s (altfel iOS cântă sunetul implicit).
"""
import os
import shutil
import subprocess
import sys
import wave

import numpy as np

RATA = 44100
DURATA = 2.5          # secunde
VARF_DB = -3.0        # dBFS
ATAC = 0.012          # secunde, intrare moale (fără „clic”)

# (frecvența Hz, amplitudinea relativă, timpul de stingere τ în secunde)
PARTIALI = [
    (528.0, 1.00, 0.95),
    (529.5, 0.35, 0.80),   # bătaia lentă cu fundamentala
    (1320.0, 0.45, 0.45),  # parțialul inarmonic, mai scurt
    (1322.0, 0.15, 0.35),
    (2210.0, 0.06, 0.18),  # o scânteie scurtă la lovire
]

AICI = os.path.dirname(os.path.abspath(__file__))
APP = os.path.dirname(AICI)
WAV = os.path.join(APP, 'android', 'app', 'src', 'main', 'res', 'raw', 'clopotel.wav')
CAF = os.path.join(APP, 'ios', 'App', 'App', 'clopotel.caf')


def sintetizeaza() -> np.ndarray:
    t = np.arange(int(RATA * DURATA)) / RATA
    s = np.zeros_like(t)
    for f, a, tau in PARTIALI:
        s += a * np.sin(2 * np.pi * f * t) * np.exp(-t / tau)
    # atac moale (jumătate de cosinus) și o ieșire liniară în ultimele 150 ms, ca să se termine exact în liniște
    atac = int(RATA * ATAC)
    s[:atac] *= 0.5 - 0.5 * np.cos(np.linspace(0, np.pi, atac))
    coada = int(RATA * 0.15)
    s[-coada:] *= np.linspace(1, 0, coada)
    varf = 10 ** (VARF_DB / 20)
    return s / np.max(np.abs(s)) * varf


def scrie_wav(cale: str, s: np.ndarray) -> None:
    os.makedirs(os.path.dirname(cale), exist_ok=True)
    pcm = np.round(s * 32767).astype('<i2')
    with wave.open(cale, 'wb') as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(RATA)
        w.writeframes(pcm.tobytes())


def scrie_caf(wav: str, caf: str) -> bool:
    if shutil.which('afconvert'):   # macOS
        cmd = ['afconvert', '-f', 'caff', '-d', 'LEI16', wav, caf]
    elif shutil.which('ffmpeg'):
        cmd = ['ffmpeg', '-y', '-loglevel', 'error', '-i', wav, '-c:a', 'pcm_s16le', '-f', 'caf', caf]
    else:
        print('Lipsește ffmpeg/afconvert: clopotel.caf NU a fost refăcut.', file=sys.stderr)
        return False
    subprocess.run(cmd, check=True)
    return True


def main() -> None:
    s = sintetizeaza()
    scrie_wav(WAV, s)
    print(f'{os.path.relpath(WAV, APP)}: {os.path.getsize(WAV) // 1024} KB')
    if scrie_caf(WAV, CAF):
        print(f'{os.path.relpath(CAF, APP)}: {os.path.getsize(CAF) // 1024} KB')


if __name__ == '__main__':
    main()
