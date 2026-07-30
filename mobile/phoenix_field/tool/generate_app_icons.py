#!/usr/bin/env python3
"""Genera iconos de app Phoenix Campo a partir del logo web."""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
REPO_ROOT = ROOT.parents[1]
LOGO_PATH = REPO_ROOT / "public" / "images" / "phoenix-logo.png"
ASSETS_DIR = ROOT / "assets" / "images"
WEB_ICONS_DIR = ROOT / "web" / "icons"

BG_RGBA = (15, 23, 42, 255)  # #0F172A — Phoenix surface
LOGO_SCALE = 0.72  # dentro del lienzo cuadrado
FOREGROUND_SCALE = 0.58  # zona segura adaptive icon (~66%)


def _fit_logo(canvas: Image.Image, logo: Image.Image, scale: float) -> Image.Image:
    size = canvas.size[0]
    target = int(size * scale)
    ratio = min(target / logo.width, target / logo.height)
    resized = logo.resize(
        (max(1, int(logo.width * ratio)), max(1, int(logo.height * ratio))),
        Image.Resampling.LANCZOS,
    )
    x = (size - resized.width) // 2
    y = (size - resized.height) // 2
    canvas.alpha_composite(resized, (x, y))
    return canvas


def _make_icon(size: int, *, with_background: bool, scale: float) -> Image.Image:
    if with_background:
        canvas = Image.new("RGBA", (size, size), BG_RGBA)
    else:
        canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    logo = Image.open(LOGO_PATH).convert("RGBA")
    return _fit_logo(canvas, logo, scale)


def _save_png(path: Path, image: Image.Image) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, format="PNG", optimize=True)
    print(f"  wrote {path.relative_to(ROOT)}")


def main() -> None:
    if not LOGO_PATH.exists():
        raise SystemExit(f"Logo no encontrado: {LOGO_PATH}")

    print("Generando fuentes de icono…")
    app_icon = _make_icon(1024, with_background=True, scale=LOGO_SCALE)
    foreground = _make_icon(1024, with_background=False, scale=FOREGROUND_SCALE)

    _save_png(ASSETS_DIR / "app_icon.png", app_icon)
    _save_png(ASSETS_DIR / "app_icon_foreground.png", foreground)

    print("Generando iconos web…")
    for size, name in (
        (192, "Icon-192.png"),
        (512, "Icon-512.png"),
        (192, "Icon-maskable-192.png"),
        (512, "Icon-maskable-512.png"),
    ):
        _save_png(WEB_ICONS_DIR / name, _make_icon(size, with_background=True, scale=LOGO_SCALE))
    _save_png(ROOT / "web" / "favicon.png", _make_icon(48, with_background=True, scale=LOGO_SCALE))

    print("Listo. Ejecuta: dart run flutter_launcher_icons")


if __name__ == "__main__":
    main()
