# -*- coding: utf-8 -*-
"""
Convierte los manuales Markdown de docs/ a Word (.docx) con portada,
índice, encabezado, numeración e imágenes con pie de figura.

Uso:
    python tools/manuales_word.py
"""
from pathlib import Path
import re
import sys

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

RAIZ = Path(__file__).resolve().parent.parent
DOCS = RAIZ / "docs"
SALIDA = DOCS / "word"
SALIDA.mkdir(parents=True, exist_ok=True)

BROWN = "4A2C17"
GOLD = "B07A3A"
CREAM = "F6EFE5"
LIGHT = "FBF7F0"
GRAY = "6B5A4A"
WHITE = "FFFFFF"

MANUALES = [
    {
        "entrada": DOCS / "manual.md",
        "salida": SALIDA / "Manual_Usuario_CoffeeAndTea_v1.0.docx",
        "titulo": "Manual de usuario",
        "subtitulo": "Pedidos, carrito, pago en mostrador y consulta de estado",
        "publico": "Visitantes y clientes",
        "cabecera": "CoffeeAndtea · Manual de usuario",
    },
    {
        "entrada": DOCS / "manual-tecnico.md",
        "salida": SALIDA / "Manual_Tecnico_CoffeeAndTea_v1.0.docx",
        "titulo": "Manual técnico",
        "subtitulo": "Operación, administración, instalación y mantenimiento",
        "publico": "Encargados y administradores",
        "cabecera": "CoffeeAndtea · Manual técnico",
    },
]


# ---------------------------------------------------------------- utilidades
def shade(celda, color):
    tcPr = celda._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color)
    tcPr.append(shd)


def sombrear_parrafo(parrafo, color):
    pPr = parrafo._p.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color)
    pPr.append(shd)


def borde_izquierdo(parrafo, color, grosor=18):
    pPr = parrafo._p.get_or_add_pPr()
    pbdr = OxmlElement("w:pBdr")
    left = OxmlElement("w:left")
    left.set(qn("w:val"), "single")
    left.set(qn("w:sz"), str(grosor))
    left.set(qn("w:space"), "8")
    left.set(qn("w:color"), color)
    pbdr.append(left)
    pPr.append(pbdr)


def margenes_celda(celda, arriba=70, abajo=70, izq=90, der=90):
    tcPr = celda._tc.get_or_add_tcPr()
    mar = OxmlElement("w:tcMar")
    for lado, valor in (("top", arriba), ("bottom", abajo), ("left", izq), ("right", der)):
        el = OxmlElement(f"w:{lado}")
        el.set(qn("w:w"), str(valor))
        el.set(qn("w:type"), "dxa")
        mar.append(el)
    tcPr.append(mar)


def repetir_encabezado(fila):
    trPr = fila._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def no_partir_fila(fila):
    trPr = fila._tr.get_or_add_trPr()
    el = OxmlElement("w:cantSplit")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def campo(parrafo, instruccion):
    """Inserta un campo de Word (PAGE, TOC...) en el párrafo."""
    run = parrafo.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = instruccion
    sep = OxmlElement("w:fldChar")
    sep.set(qn("w:fldCharType"), "separate")
    texto = OxmlElement("w:t")
    texto.text = "1"
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    for el in (begin, instr, sep, texto, end):
        run._r.append(el)
    return run


# ---------------------------------------------------------------- estilos
def preparar_estilos(doc):
    normal = doc.styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(10.5)
    normal.font.color.rgb = RGBColor(0x33, 0x33, 0x33)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.12

    for nombre, tam, color, antes, despues in [
        ("Heading 1", 18, BROWN, 18, 8),
        ("Heading 2", 14, GOLD, 14, 6),
        ("Heading 3", 11.5, BROWN, 10, 4),
    ]:
        st = doc.styles[nombre]
        st.font.name = "Calibri"
        st.font.size = Pt(tam)
        st.font.bold = True
        st.font.color.rgb = RGBColor.from_string(color)
        st.paragraph_format.space_before = Pt(antes)
        st.paragraph_format.space_after = Pt(despues)
        st.paragraph_format.keep_with_next = True

    for nombre in ("List Bullet",):
        st = doc.styles[nombre]
        st.font.name = "Calibri"
        st.font.size = Pt(10.5)
        st.paragraph_format.space_after = Pt(2)


def configurar_pagina(doc, cabecera):
    seccion = doc.sections[0]
    seccion.page_width = Cm(21)
    seccion.page_height = Cm(29.7)
    seccion.top_margin = Cm(2)
    seccion.bottom_margin = Cm(1.8)
    seccion.left_margin = Cm(2)
    seccion.right_margin = Cm(2)
    seccion.header_distance = Cm(1)
    seccion.footer_distance = Cm(0.9)
    seccion.different_first_page_header_footer = True

    hp = seccion.header.paragraphs[0]
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = hp.add_run(cabecera)
    run.font.size = Pt(8)
    run.font.color.rgb = RGBColor.from_string(GRAY)

    fp = seccion.footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = fp.add_run("CoffeeAndtea · Página ")
    r.font.size = Pt(8)
    r.font.color.rgb = RGBColor.from_string(GRAY)
    campo(fp, "PAGE").font.size = Pt(8)
    for run in fp.runs:
        run.font.color.rgb = RGBColor.from_string(GRAY)


# ---------------------------------------------------------------- portada
def portada(doc, meta, bloques):
    for _ in range(2):
        doc.add_paragraph()

    if (RAIZ / "public/media/logo-coffeeandtea.png").exists():
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.add_run().add_picture(str(RAIZ / "public/media/logo-coffeeandtea.png"), width=Cm(3.4))

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("COFFEEANDTEA")
    r.font.size = Pt(11)
    r.font.bold = True
    r.font.color.rgb = RGBColor.from_string(GOLD)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)
    r = p.add_run(meta["titulo"])
    r.font.size = Pt(34)
    r.font.bold = True
    r.font.color.rgb = RGBColor.from_string(BROWN)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run(meta["subtitulo"])
    r.font.size = Pt(13)
    r.font.color.rgb = RGBColor.from_string(GRAY)

    doc.add_paragraph()
    tabla = doc.add_table(rows=0, cols=2)
    tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
    datos = [
        ("Dirigido a", meta["publico"]),
        ("Sistema", "CoffeeAndtea · tienda y gestión para cafetería"),
        ("Stack", "PHP 8.3 · PostgreSQL 18 · HTML, CSS y JavaScript"),
        ("Versión", "1.0"),
        ("Fecha", "25 de noviembre de 2026"),
        ("Autor", "Kevin Santiago Vega Pérez"),
    ]
    for clave, valor in datos:
        celdas = tabla.add_row().cells
        celdas[0].width = Cm(3.4)
        celdas[1].width = Cm(10.6)
        p0 = celdas[0].paragraphs[0]
        r0 = p0.add_run(clave)
        r0.font.bold = True
        r0.font.size = Pt(9)
        r0.font.color.rgb = RGBColor.from_string(BROWN)
        p1 = celdas[1].paragraphs[0]
        r1 = p1.add_run(valor)
        r1.font.size = Pt(9)
        r1.font.color.rgb = RGBColor.from_string(GRAY)

    # Resumen tomado del primer párrafo del manual.
    resumen = ""
    for bloque in bloques:
        if bloque[0] == "p" and len(bloque[1]) > 60:
            resumen = re.sub(r"\*\*|\*|`", "", bloque[1])
            break
    if resumen:
        doc.add_paragraph()
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(1)
        p.paragraph_format.right_indent = Cm(1)
        sombrear_parrafo(p, CREAM)
        r = p.add_run(resumen)
        r.font.size = Pt(9.5)
        r.font.color.rgb = RGBColor.from_string(GRAY)


def indice(doc, secciones):
    doc.add_page_break()
    doc.add_heading("Contenido", level=1)
    for texto in secciones:
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(4)
        r = p.add_run(texto)
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor.from_string(BROWN)
    doc.add_page_break()


# ---------------------------------------------------------------- inline
PATRON_INLINE = re.compile(r"(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)")


def escribir_inline(parrafo, texto, tam=10.5, color=None):
    for parte in PATRON_INLINE.split(texto):
        if not parte:
            continue
        if parte.startswith("**") and parte.endswith("**"):
            run = parrafo.add_run(parte[2:-2])
            run.bold = True
        elif parte.startswith("*") and parte.endswith("*"):
            run = parrafo.add_run(parte[1:-1])
            run.italic = True
        elif parte.startswith("`") and parte.endswith("`"):
            run = parrafo.add_run(parte[1:-1])
            run.font.name = "Consolas"
            run.font.size = Pt(tam - 1.2)
            run.font.color.rgb = RGBColor(0x6B, 0x3D, 0x18)
        else:
            run = parrafo.add_run(parte)
        run.font.size = Pt(tam)
        if color:
            run.font.color.rgb = RGBColor.from_string(color)


# ---------------------------------------------------------------- parser
def parsear(md):
    bloques = []
    lineas = md.splitlines()
    i = 0
    while i < len(lineas):
        linea = lineas[i]
        limpio = linea.rstrip()

        if limpio.strip() == "":
            i += 1
            continue

        if limpio.strip().startswith("```"):
            i += 1
            codigo = []
            while i < len(lineas) and not lineas[i].strip().startswith("```"):
                codigo.append(lineas[i])
                i += 1
            i += 1
            bloques.append(("codigo", "\n".join(codigo)))
            continue

        if re.match(r"^#{1,3}\s", limpio):
            nivel = len(limpio) - len(limpio.lstrip("#"))
            bloques.append((f"h{nivel}", limpio[nivel:].strip()))
            i += 1
            continue

        if re.match(r"^-{3,}\s*$", limpio):
            bloques.append(("hr", ""))
            i += 1
            continue

        if limpio.startswith(">"):
            cita = []
            while i < len(lineas) and lineas[i].strip().startswith(">"):
                cita.append(lineas[i].strip()[1:].strip())
                i += 1
            bloques.append(("cita", " ".join(c for c in cita if c)))
            continue

        if limpio.startswith("|"):
            filas = []
            while i < len(lineas) and lineas[i].strip().startswith("|"):
                fila = [c.strip() for c in lineas[i].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{2,}:?", c) for c in fila if c):
                    filas.append(fila)
                i += 1
            if filas:
                bloques.append(("tabla", filas))
            continue

        m = re.match(r"^!\[(.*?)\]\((.*?)\)\s*$", limpio)
        if m:
            bloques.append(("imagen", (m.group(1), m.group(2))))
            i += 1
            continue

        if re.match(r"^\s*[-*]\s+", limpio):
            items = []
            while i < len(lineas):
                actual = lineas[i]
                if re.match(r"^\s*[-*]\s+", actual):
                    items.append(re.sub(r"^\s*[-*]\s+", "", actual).strip())
                    i += 1
                elif items and actual[:1].isspace() and actual.strip():
                    items[-1] += " " + actual.strip()
                    i += 1
                else:
                    break
            bloques.append(("ul", items))
            continue

        if re.match(r"^\s*\d+\.\s+", limpio):
            items = []
            while i < len(lineas):
                actual = lineas[i]
                if re.match(r"^\s*\d+\.\s+", actual):
                    items.append(re.sub(r"^\s*\d+\.\s+", "", actual).strip())
                    i += 1
                elif items and actual[:1].isspace() and actual.strip():
                    items[-1] += " " + actual.strip()
                    i += 1
                else:
                    break
            bloques.append(("ol", items))
            continue

        parrafo = [limpio.strip()]
        i += 1
        while (
            i < len(lineas)
            and lineas[i].strip()
            and not re.match(r"^(#{1,3}\s|>\s*|\||```|!\[|-\s|\d+\.\s)", lineas[i].strip())
            and not re.match(r"^-{3,}\s*$", lineas[i].strip())
        ):
            parrafo.append(lineas[i].strip())
            i += 1
        bloques.append(("p", " ".join(parrafo)))
    return bloques


# ---------------------------------------------------------------- contenido
def agregar_tabla(doc, filas):
    columnas = max(len(f) for f in filas)
    tabla = doc.add_table(rows=1, cols=columnas)
    tabla.style = "Table Grid"
    tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
    tabla.autofit = False

    # Ancho proporcional al contenido de cada columna.
    pesos = []
    for idx in range(columnas):
        largos = [len(re.sub(r"\*\*|\*|`", "", f[idx])) for f in filas if idx < len(f)]
        pesos.append(max(max(largos) if largos else 8, 6))
    total_peso = sum(pesos)
    anchos = [max(2.3, 16.6 * peso / total_peso) for peso in pesos]
    for idx, ancho in enumerate(anchos):
        tabla.columns[idx].width = Cm(ancho)

    encabezado = tabla.rows[0]
    for idx in range(columnas):
        texto = filas[0][idx] if idx < len(filas[0]) else ""
        celda = encabezado.cells[idx]
        celda.width = Cm(anchos[idx])
        celda.text = ""
        p = celda.paragraphs[0]
        p.paragraph_format.space_after = Pt(1)
        escribir_inline(p, texto, tam=8.5, color=WHITE)
        for run in p.runs:
            run.bold = True
        shade(celda, BROWN)
        margenes_celda(celda)
    repetir_encabezado(encabezado)

    for numero, fila in enumerate(filas[1:]):
        celdas = tabla.add_row().cells
        for idx in range(columnas):
            texto = fila[idx] if idx < len(fila) else ""
            celda = celdas[idx]
            celda.width = Cm(anchos[idx])
            celda.text = ""
            p = celda.paragraphs[0]
            p.paragraph_format.space_after = Pt(1)
            escribir_inline(p, texto, tam=8.5)
            if numero % 2 == 1:
                shade(celda, LIGHT)
            margenes_celda(celda)
        no_partir_fila(tabla.rows[-1])

    doc.add_paragraph().paragraph_format.space_after = Pt(2)


def agregar_imagen(doc, ruta, leyenda):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.keep_with_next = True
    ancho = Cm(16.5)
    try:
        from PIL import Image

        with Image.open(ruta) as im:
            proporcion = im.height / im.width
        if proporcion > 1.05:
            ancho = Cm(8.5)
    except Exception:
        pass
    p.add_run().add_picture(str(ruta), width=ancho)


def agregar_codigo(doc, texto):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Cm(0.4)
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.line_spacing = 1.0
    sombrear_parrafo(p, "F3EFE8")
    for indice, linea in enumerate(texto.splitlines()):
        if indice:
            p.add_run().add_break()
        run = p.add_run(linea if linea else " ")
        run.font.name = "Consolas"
        run.font.size = Pt(8.3)
        run.font.color.rgb = RGBColor(0x3A, 0x2A, 0x1C)


def agregar_cita(doc, texto):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Cm(0.5)
    p.paragraph_format.right_indent = Cm(0.3)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(10)
    sombrear_parrafo(p, "FFF6E8")
    borde_izquierdo(p, GOLD)
    escribir_inline(p, texto, tam=9.5, color="6B4C33")


def construir(doc, bloques, meta):
    primera = True
    for tipo, dato in bloques:
        if tipo == "h1":
            if primera:
                primera = False
                continue
            doc.add_page_break()
            doc.add_heading(dato, level=1)
        elif tipo == "h2":
            doc.add_heading(dato, level=2)
        elif tipo == "h3":
            doc.add_heading(dato, level=3)
        elif tipo == "p":
            # Pie de figura: párrafo que solo contiene énfasis y empieza por "Figura".
            if re.fullmatch(r"\*[^*].*\*", dato) and dato[1:8].lower().startswith("figura"):
                p = doc.add_paragraph()
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                p.paragraph_format.space_after = Pt(12)
                escribir_inline(p, dato[1:-1], tam=8.5, color=GRAY)
                for run in p.runs:
                    run.italic = True
                continue
            p = doc.add_paragraph()
            escribir_inline(p, dato)
        elif tipo == "ul":
            for item in dato:
                p = doc.add_paragraph()
                p.paragraph_format.left_indent = Cm(0.75)
                p.paragraph_format.first_line_indent = Cm(-0.4)
                p.paragraph_format.space_after = Pt(3)
                escribir_inline(p, "•  " + item)
        elif tipo == "ol":
            for numero, item in enumerate(dato, start=1):
                p = doc.add_paragraph()
                p.paragraph_format.left_indent = Cm(0.75)
                p.paragraph_format.first_line_indent = Cm(-0.35)
                p.paragraph_format.space_after = Pt(2)
                escribir_inline(p, f"{numero}. {item}")
        elif tipo == "cita":
            agregar_cita(doc, dato)
        elif tipo == "codigo":
            agregar_codigo(doc, dato)
        elif tipo == "tabla":
            agregar_tabla(doc, dato)
        elif tipo == "imagen":
            alt, src = dato
            ruta = RAIZ / "public" / src if not src.startswith("http") else None
            if ruta and ruta.exists():
                agregar_imagen(doc, ruta, alt)
        elif tipo == "hr":
            p = doc.add_paragraph()
            p.paragraph_format.space_after = Pt(4)


# ---------------------------------------------------------------- main
def generar(meta):
    md = meta["entrada"].read_text(encoding="utf-8")
    bloques = parsear(md)
    secciones = [dato for tipo, dato in bloques if tipo == "h2"]

    doc = Document()
    preparar_estilos(doc)
    configurar_pagina(doc, meta["cabecera"])
    doc.core_properties.title = f"{meta['titulo']} — CoffeeAndtea"
    doc.core_properties.author = "Kevin Santiago Vega Pérez"
    doc.core_properties.subject = "Documentación del proyecto CoffeeAndtea"

    portada(doc, meta, bloques)
    indice(doc, secciones)
    construir(doc, bloques, meta)

    doc.save(meta["salida"])
    return meta["salida"]


if __name__ == "__main__":
    for meta in MANUALES:
        ruta = generar(meta)
        tam = ruta.stat().st_size / 1024
        print(f"{ruta.name:45} {tam:8.1f} KB")
