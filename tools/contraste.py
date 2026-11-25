# -*- coding: utf-8 -*-
"""
Comprueba los contrastes de la paleta de CoffeeAndtea contra WCAG 2.1 AA.

Uso:
    python tools/contraste.py

Devuelve código 1 si algún par no cumple el mínimo.
"""
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# (nombre, color de texto, color de fondo, mínimo exigido)
PARES = [
    ("producto-categoria #7d5a38 / #fffdf9", "#7d5a38", "#fffdf9", 4.5),
    ("producto-stock #7a6a5a / #fffdf9", "#7a6a5a", "#fffdf9", 4.5),
    ("texto secundario #785d43 / crema", "#785d43", "#f6efe5", 4.5),
    ("texto secundario #785d43 / crema-2", "#785d43", "#efe3d3", 4.5),
    ("texto secundario #785d43 / blanco", "#785d43", "#fffdf9", 4.5),
    ("texto principal #5c4433 / crema", "#5c4433", "#f6efe5", 4.5),
    ("foco #8a5a25 / crema", "#8a5a25", "#f6efe5", 3.0),
    ("foco #8a5a25 / blanco", "#8a5a25", "#fffdf9", 3.0),
    ("menu activo #2f1c10 / ambar", "#2f1c10", "#d9a05b", 4.5),
    ("footer #7b6a5c / blanco", "#7b6a5c", "#fffdf9", 4.5),
    ("error #a92c38 / blanco", "#a92c38", "#fffdf9", 4.5),
    ("exito #1e7e34 / verde-suave", "#1e7e34", "#e8f5ec", 4.5),
]


def canal(valor: int) -> float:
    v = valor / 255
    return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4


def luminancia(hexadecimal: str) -> float:
    r, g, b = (int(hexadecimal[i:i + 2], 16) for i in (1, 3, 5))
    return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b)


def contraste(texto: str, fondo: str) -> float:
    a, b = luminancia(texto), luminancia(fondo)
    alto, bajo = max(a, b), min(a, b)
    return (alto + 0.05) / (bajo + 0.05)


def main() -> int:
    fallos = 0
    ancho = max(len(nombre) for nombre, *_ in PARES)
    print(f"{'Par de colores'.ljust(ancho)}  Ratio   Mínimo  Estado")
    print("-" * (ancho + 26))
    for nombre, texto, fondo, minimo in PARES:
        ratio = contraste(texto, fondo)
        estado = "CUMPLE" if ratio >= minimo else "FALLA"
        if ratio < minimo:
            fallos += 1
        print(f"{nombre.ljust(ancho)}  {ratio:5.2f}   {minimo:4.1f}    {estado}")
    print()
    print(f"Pares que fallan: {fallos} de {len(PARES)}")
    return 1 if fallos else 0


if __name__ == "__main__":
    raise SystemExit(main())
