# -*- coding: utf-8 -*-
"""
Convierte las capturas de tools/_capturas a JPG optimizados en
public/media/capturas y ajusta los diagramas para el manual.
"""
from pathlib import Path
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / "tools/_capturas"
DESTINO = RAIZ / "public/media/capturas"
DIAGRAMAS = RAIZ / "public/media/capturas"

ANCHO_MAX = 1440
CALIDAD = 85

DESTINO.mkdir(parents=True, exist_ok=True)

total = 0
for png in sorted(ORIGEN.glob("*.png")):
    destino = DESTINO / (png.stem + ".jpg")
    with Image.open(png) as im:
        im = im.convert("RGB")
        if im.width > ANCHO_MAX:
            alto = round(im.height * ANCHO_MAX / im.width)
            im = im.resize((ANCHO_MAX, alto), Image.LANCZOS)
        im.save(destino, "JPEG", quality=CALIDAD, optimize=True, progressive=True)
    total += destino.stat().st_size
    print(f"{destino.name:28} {im.width}x{im.height}  {destino.stat().st_size // 1024} KB")

for nombre in ("arquitectura", "flujo-pedido"):
    ruta = DIAGRAMAS / f"{nombre}.png"
    if not ruta.exists():
        continue
    with Image.open(ruta) as im:
        im = im.convert("RGB")
        ancho = 1600
        alto = round(im.height * ancho / im.width)
        im = im.resize((ancho, alto), Image.LANCZOS)
        # Se guarda como PNG para conservar la nitidez del texto.
        im.save(ruta, "PNG", optimize=True)
    total += ruta.stat().st_size
    print(f"{ruta.name:28} {im.width}x{im.height}  {ruta.stat().st_size // 1024} KB")

print(f"\nTotal: {total / 1024 / 1024:.2f} MB")
