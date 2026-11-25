import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const OUT = 'public/media/capturas';
mkdirSync(OUT, { recursive: true });

const base = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    width: 1240px; padding: 44px 48px;
    font-family: "Segoe UI", system-ui, sans-serif;
    background: #fbf7f1; color: #3b2a1c;
  }
  h1 { font-size: 26px; color: #4a2c17; letter-spacing: -0.02em; }
  h1 span { color: #b07a3a; }
  .sub { margin-top: 6px; font-size: 14px; color: #7a6552; }
  .fila { display: flex; align-items: stretch; gap: 14px; margin-top: 30px; }
  .caja {
    flex: 1; background: #fffdf9; border: 1px solid #e6d8c6; border-radius: 14px;
    padding: 16px 16px 18px; box-shadow: 0 6px 18px rgba(74,44,23,0.07);
  }
  .caja .et { font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; color: #b07a3a; font-weight: 700; }
  .caja .tt { margin-top: 6px; font-size: 15px; font-weight: 700; color: #4a2c17; line-height: 1.25; }
  .caja .dd { margin-top: 6px; font-size: 12.5px; color: #7a6552; line-height: 1.4; }
  .flecha { align-self: center; color: #c89a5c; font-size: 22px; font-weight: 700; }
  .pie {
    margin-top: 26px; display: flex; gap: 14px;
  }
  .pie div {
    flex: 1; background: #f6efe5; border: 1px dashed #dcc7ab; border-radius: 12px;
    padding: 12px 14px; font-size: 12.5px; color: #6b5847;
  }
  .pie b { color: #4a2c17; }
  .etapa {
    flex: 1; background: #fffdf9; border: 1px solid #e6d8c6; border-radius: 14px;
    padding: 14px; text-align: center; position: relative;
  }
  .etapa .n {
    width: 26px; height: 26px; line-height: 26px; border-radius: 50%;
    background: #4a2c17; color: #f6e7d5; font-size: 12px; font-weight: 700;
    margin: 0 auto 8px;
  }
  .etapa .t { font-size: 14px; font-weight: 700; color: #4a2c17; }
  .etapa .d { margin-top: 5px; font-size: 11.5px; color: #7a6552; line-height: 1.35; }
  .etapa.hecha { background: #f6efe5; border-color: #dcc7ab; }
  .nota {
    margin-top: 18px; background: #fff6e8; border-left: 4px solid #d9a05b;
    border-radius: 0 10px 10px 0; padding: 12px 16px; font-size: 12.5px; color: #6b4c33;
  }
  .nota b { color: #4a2c17; }
`;

const arquitectura = `<!doctype html><html lang="es"><head><meta charset="utf-8"><style>${base}</style></head><body>
  <h1>Arquitectura de <span>CoffeeAndtea</span></h1>
  <p class="sub">Cada petición recorre cinco capas. No hay framework ni ORM: el SQL y las transacciones son explícitos.</p>
  <div class="fila">
    <div class="caja">
      <div class="et">1 · Cliente</div>
      <div class="tt">Navegador</div>
      <div class="dd">HTML, CSS y JavaScript. Formularios con token CSRF.</div>
    </div>
    <div class="flecha">→</div>
    <div class="caja">
      <div class="et">2 · Presentación</div>
      <div class="tt">Apache · public/*.php</div>
      <div class="dd">Un archivo por página: tienda, carrito, gestión, admin, anexos.</div>
    </div>
    <div class="flecha">→</div>
    <div class="caja">
      <div class="et">3 · Núcleo</div>
      <div class="tt">bootstrap.php</div>
      <div class="dd">Carga .env, sesión, cabeceras, CSRF y la conexión PDO.</div>
    </div>
    <div class="flecha">→</div>
    <div class="caja">
      <div class="et">4 · Lógica</div>
      <div class="tt">app/php/lib/*.php</div>
      <div class="dd">auth, pedidos, caja, stock, imágenes, auditoría y reportes.</div>
    </div>
    <div class="flecha">→</div>
    <div class="caja">
      <div class="et">5 · Datos</div>
      <div class="tt">PostgreSQL 18</div>
      <div class="dd">14 tablas, 19 relaciones FK, triggers y procedimientos.</div>
    </div>
  </div>
  <div class="pie">
    <div><b>Vistas compartidas:</b> app/php/vistas/plantilla.php y panel_pedido.php</div>
    <div><b>Configuración:</b> .env (fuera de public/) y storage/ para logs</div>
    <div><b>Seguridad:</b> sesión versionada, bcrypt, CSRF y RBAC por rol</div>
  </div>
</body></html>`;

const flujo = `<!doctype html><html lang="es"><head><meta charset="utf-8"><style>${base}</style></head><body>
  <h1>Flujo de un <span>pedido</span></h1>
  <p class="sub">El stock se descuenta al confirmar el pago, nunca al crear el pedido. Todo queda auditado.</p>
  <div class="fila">
    <div class="etapa"><div class="n">1</div><div class="t">Pedido creado</div><div class="d">PENDIENTE<br>Sin descontar stock</div></div>
    <div class="flecha">→</div>
    <div class="etapa"><div class="n">2</div><div class="t">Pago confirmado</div><div class="d">PAGADO<br>Stock descontado<br>Pago y caja registrados</div></div>
    <div class="flecha">→</div>
    <div class="etapa"><div class="n">3</div><div class="t">En preparación</div><div class="d">El pedido se está<br>preparando</div></div>
    <div class="flecha">→</div>
    <div class="etapa"><div class="n">4</div><div class="t">Listo</div><div class="d">Puedes pasar<br>a recogerlo</div></div>
    <div class="flecha">→</div>
    <div class="etapa"><div class="n">5</div><div class="t">Entregado</div><div class="d">Pedido cerrado<br>No se puede cancelar</div></div>
  </div>
  <div class="nota">
    <b>Cancelación:</b> exige motivo y nunca aplica a un pedido entregado. Reembolso del <b>100 %</b> antes de preparación
    y del <b>50 %</b> en preparación o listo; el stock vuelve al inventario y, si fue en efectivo, se registra el egreso en caja.
  </div>
</body></html>`;

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1240, height: 600 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();

for (const [nombre, html] of [['arquitectura', arquitectura], ['flujo-pedido', flujo]]) {
  await page.setViewportSize({ width: 1240, height: 600 });
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForTimeout(250);
  const alto = await page.evaluate(() => Math.ceil(document.body.getBoundingClientRect().height));
  await page.setViewportSize({ width: 1240, height: alto });
  await page.waitForTimeout(150);
  await page.screenshot({ path: `${OUT}/${nombre}.png` });
  console.log('diagrama:', nombre, 'alto', alto);
}

await browser.close();
