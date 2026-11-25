# -*- coding: utf-8 -*-
"""
Optimiza las imágenes de producto y genera su versión WebP.

- JPG: máximo 1000 px de ancho, calidad 82, progresivo.
- WebP: mismo tamaño, calidad 82, como hermano del JPG.
- Logo: se conserva el PNG y se genera un WebP con transparencia.

Uso:
    python tools/optimizar_imagenes.py
"""
from pathlib import Path
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
PRODUCTOS = RAIZ / "public/media/productos"
MEDIA = RAIZ / "public/media"

ANCHO_MAX = 1000
CALIDAD = 82

antes = despues = 0

for jpg in sorted(PRODUCTOS.glob("*.jpg")):
    antes += jpg.stat().st_size
    with Image.open(jpg) as im:
        im = im.convert("RGB")
        if im.width > ANCHO_MAX:
            alto = round(im.height * ANCHO_MAX / im.width)
            im = im.resize((ANCHO_MAX, alto), Image.LANCZOS)
        im.save(jpg, "JPEG", quality=CALIDAD, optimize=True, progressive=True)
        webp = jpg.with_suffix(".webp")
        im.save(webp, "WEBP", quality=CALIDAD, method=6)
        if webp.stat().st_size >= jpg.stat().st_size * 0.92:
            webp.unlink()
            print(f"{jpg.name:26} {im.width}x{im.height}  jpg {jpg.stat().st_size // 1024:4} KB  webp descartado (no ahorra)")
            despues += jpg.stat().st_size
            continue
    despues += jpg.stat().st_size + webp.stat().st_size
    print(f"{jpg.name:26} {im.width}x{im.height}  jpg {jpg.stat().st_size // 1024:4} KB  webp {webp.stat().st_size // 1024:4} KB")

logo = MEDIA / "logo-coffeeandtea.png"
if logo.exists():
    antes += logo.stat().st_size
    with Image.open(logo) as im:
        im = im.convert("RGBA")
        webp = logo.with_suffix(".webp")
        im.save(webp, "WEBP", quality=85, method=6)
    despues += logo.stat().st_size + webp.stat().st_size
    print(f"{logo.name:26} {im.width}x{im.height}  png {logo.stat().st_size // 1024:4} KB  webp {webp.stat().st_size // 1024:4} KB")

print(f"\nAntes: {antes / 1024:.1f} KB · Después (jpg+webp): {despues / 1024:.1f} KB")
