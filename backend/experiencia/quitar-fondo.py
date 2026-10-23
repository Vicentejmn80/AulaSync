"""Quita el fondo claro de los videos de Aulo y conserva el audio.

Salida:
  public/videos/experiencia/robot-escribiendo.webm   (VP9 con alfa, Chrome/Android)
  public/videos/experiencia/robot-saludando.webm
  public/videos/experiencia/robot-festejando.webm
  public/videos/experiencia/aulo-espera.png          (primer plano del modo espera)

Safari/iPad necesita HEVC con alfa. En Windows ffmpeg no puede generarlo
(hace falta videotoolbox en macOS). Comando, con los PNG ya recortados:

  ffmpeg -framerate 24 -i f%04d.png -i original.mp4 \\
    -map 0:v:0 -map 1:a:0 \\
    -c:v hevc_videotoolbox -allow_sw 1 -alpha_quality 0.75 -vtag hvc1 \\
    -c:a aac -b:a 160k robot.mov

Uso:
  python experiencia/quitar-fondo.py
"""
import glob
import os
import re
import shutil
import subprocess
import tempfile

import cv2
import numpy as np
from imageio_ffmpeg import get_ffmpeg_exe

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
VIDEO_DIR = os.path.join(ROOT, "public", "videos", "experiencia")
FFMPEG = get_ffmpeg_exe()
CLIPS = [
    ("robot-escribiendo.mp4", "robot-escribiendo.webm", "aulo-espera.png"),
    ("robot-saludando.mp4", "robot-saludando.webm", None),
    ("robot-festejando.mp4", "robot-festejando.webm", None),
]


def fps_of(path):
    probe = subprocess.run([FFMPEG, "-i", path], capture_output=True, text=True)
    match = re.search(r"(\d+(?:\.\d+)?) fps", probe.stderr)
    return match.group(1) if match else "24"


def flood(image, chroma_max):
    height, width = image.shape[:2]
    chroma = image.max(axis=2).astype(np.int16) - image.min(axis=2).astype(np.int16)
    candidate = np.where(chroma <= chroma_max, np.uint8(0), np.uint8(255))
    mask = np.zeros((height + 2, width + 2), np.uint8)
    flags = cv2.FLOODFILL_MASK_ONLY | (255 << 8) | 4 | cv2.FLOODFILL_FIXED_RANGE
    seeds = []
    for x in range(0, width, 8):
        seeds.append((x, 0))
        seeds.append((x, height - 1))
    for y in range(0, height, 8):
        seeds.append((0, y))
        seeds.append((width - 1, y))
    for sx, sy in seeds:
        if candidate[sy, sx] != 0 or mask[sy + 1, sx + 1]:
            continue
        cv2.floodFill(candidate, mask, (sx, sy), 0, (0, 0, 0), (0, 0, 0), flags)
    return np.where(mask[1:-1, 1:-1] == 255, 0, 255).astype(np.uint8)


def cut(image):
    alpha = flood(image, 24)
    if float((alpha == 0).mean()) > 0.82:
        alpha = flood(image, 12)
    return alpha


def has_audio(path):
    probe = subprocess.run([FFMPEG, "-i", path], capture_output=True, text=True)
    return "Audio:" in probe.stderr


def process(src_name, webm_name, poster_name):
    src = os.path.join(VIDEO_DIR, src_name)
    dst = os.path.join(VIDEO_DIR, webm_name)
    fps = fps_of(src)
    tmp = tempfile.mkdtemp(prefix="aulo-")
    try:
        subprocess.check_call(
            [FFMPEG, "-y", "-i", src, os.path.join(tmp, "f%04d.png")],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        files = sorted(glob.glob(os.path.join(tmp, "f*.png")))
        preview_at = {max(0, len(files) // 5), len(files) // 2, max(0, (len(files) * 4) // 5)}
        preview_dir = os.path.join(ROOT, "experiencia", "previews")
        os.makedirs(preview_dir, exist_ok=True)
        for index, frame_path in enumerate(files):
            image = cv2.imread(frame_path)
            alpha = cut(image)
            bgra = cv2.cvtColor(image, cv2.COLOR_BGR2BGRA)
            bgra[:, :, 3] = alpha
            cv2.imwrite(frame_path, bgra)
            if poster_name and index == min(12, len(files) - 1):
                cv2.imwrite(os.path.join(VIDEO_DIR, poster_name), bgra)
            if index in preview_at:
                lavender = np.full_like(image, (255, 230, 246))
                weight = (alpha.astype(np.float32) / 255.0)[:, :, None]
                composite = (image.astype(np.float32) * weight + lavender.astype(np.float32) * (1 - weight)).astype(np.uint8)
                cv2.imwrite(os.path.join(preview_dir, src_name.replace(".mp4", f"-{index}.png")), composite)
        subprocess.check_call(
            [
                FFMPEG, "-y",
                "-framerate", fps,
                "-i", os.path.join(tmp, "f%04d.png"),
                "-i", src,
                "-map", "0:v:0",
                "-map", "1:a:0",
                "-c:v", "libvpx-vp9",
                "-pix_fmt", "yuva420p",
                "-auto-alt-ref", "0",
                "-b:v", "0",
                "-crf", "33",
                "-cpu-used", "4",
                "-row-mt", "1",
                "-c:a", "libopus",
                "-b:a", "128k",
                dst,
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    finally:
        shutil.rmtree(tmp, ignore_errors=True)
    print(src_name, "AUDIO" if has_audio(dst) else "SIN_AUDIO", os.path.getsize(dst))


if __name__ == "__main__":
    for clip in CLIPS:
        process(*clip)
