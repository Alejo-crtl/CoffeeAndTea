import { chromium } from '@playwright/test';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const BASE = 'http://coffeeandtea.test';
const OUT = 'tools/_capturas';
rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

const PASSWORD = {
  admin: 'Admin123!',
  encargado: 'Encargado123!',
  cliente: 'Cliente123!',
};

const sinAnimacion = '*{transition:none!important;animation:none!important;scroll-behavior:auto!important}';

async function nuevaPagina(browser) {
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    deviceScaleFactor: 2,
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
  });
  const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept());
  return { ctx, page };
}

async function cargar(page, url) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
  await page.waitForTimeout(250);
}

async function captura(page, nombre) {
  await page.screenshot({ path: join(OUT, nombre + '.png') });
  console.log('  ok:', nombre);
}

async function capturaConMargen(page, locator, nombre, margen = 26) {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(200);
  const caja = await locator.boundingBox();
  if (!caja) {
    await captura(page, nombre);
    return;
  }
  const x = Math.max(0, caja.x - margen);
  const y = Math.max(0, caja.y - margen);
  await page.screenshot({
    path: join(OUT, nombre + '.png'),
    clip: {
      x,
      y,
      width: Math.min(caja.width + margen * 2, 1280 - x),
      height: caja.height + margen * 2,
    },
  });
  console.log('  ok:', nombre);
}

async function bajarA(page, selector) {
  await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return;
    const cabecera = document.querySelector('header, .site-header, .cabecera');
    const alto = cabecera ? cabecera.getBoundingClientRect().height : 0;
    window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - alto - 8);
  }, selector);
  await page.waitForTimeout(300);
}

async function login(page, correo, password) {
  await cargar(page, '/login.php');
  await page.fill('#correo', correo);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForURL(/gestion|admin|cuenta/, { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
}

const browser = await chromium.launch();

try {
  // ---------- Sesión de invitado ----------
  console.log('Invitado');
  {
    const { ctx, page } = await nuevaPagina(browser);

    await cargar(page, '/');
    await bajarA(page, '.section-menu');
    await captura(page, 'tienda');

    await page.locator('.tarjeta-producto').first().scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);
    await page.locator('.tarjeta-producto').first().screenshot({ path: join(OUT, 'tarjeta-producto.png') });
    console.log('  ok: tarjeta-producto');

    // Agregar dos productos distintos al carrito.
    const botones = page.locator('.tarjeta-producto .form-agregar button');
    const total = await botones.count();
    await botones.nth(0).click();
    await page.waitForLoadState('networkidle').catch(() => {});
    if (total > 1) {
      await page.locator('.tarjeta-producto .form-agregar button').nth(1).click();
      await page.waitForLoadState('networkidle').catch(() => {});
    }

    await cargar(page, '/carrito.php');
    await captura(page, 'carrito');

    await cargar(page, '/checkout.php');
    await page.fill('#nombre', 'Laura Gómez');
    await page.fill('#correo', 'laura.gomez@example.com');
    await page.fill('#telefono', '3001234567');
    await page.fill('#observaciones', 'Sin azúcar en el café, por favor.');
    const enlacesPolitica = page.locator('[data-politica-enlace]');
    const totalPoliticas = await enlacesPolitica.count();
    for (let i = 0; i < totalPoliticas; i++) {
      const [popup] = await Promise.all([
        page.context().waitForEvent('page'),
        enlacesPolitica.nth(i).click(),
      ]);
      await popup.close();
    }
    await page.check('input[name="consentimiento"]');
    await page.check('input[name="acepta_cancelacion"]');
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(250);
    await captura(page, 'checkout');

    await Promise.all([
      page.waitForURL(/pedido\.php/, { timeout: 20000 }),
      page.click('form button[type="submit"]'),
    ]);
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
    await captura(page, 'pedido-creado');

    await bajarA(page, '.linea-tiempo');
    await captura(page, 'consulta-pedido');

    await cargar(page, '/pedidos-invitado.php');
    await captura(page, 'pedidos-recientes');

    await cargar(page, '/login.php');
    await captura(page, 'login');

    await cargar(page, '/registro.php');
    await captura(page, 'registro');

    await cargar(page, '/recuperar.php');
    await captura(page, 'recuperar');

    await ctx.close();
  }

  // ---------- Sesión de cliente ----------
  console.log('Cliente');
  {
    const { ctx, page } = await nuevaPagina(browser);
    await login(page, 'cliente@coffeeandtea.local', PASSWORD.cliente);
    await cargar(page, '/cuenta.php');
    await captura(page, 'mi-cuenta');
    await ctx.close();
  }

  // ---------- Sesión de administración ----------
  console.log('Administración');
  {
    const { ctx, page } = await nuevaPagina(browser);
    await login(page, 'admin@coffeeandtea.local', PASSWORD.admin);

    await cargar(page, '/gestion.php?tab=pedidos');
    await captura(page, 'gestion-pedidos');

    let detalleUrl = null;
    const abrir = page.locator('a[href*="ver="]').first();
    if (await abrir.count()) {
      detalleUrl = await abrir.getAttribute('href');
      await Promise.all([
        page.waitForURL(/ver=/, { timeout: 15000 }),
        abrir.click(),
      ]);
      await page.waitForLoadState('networkidle').catch(() => {});
      await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
      await captura(page, 'gestion-detalle');

      const formCobro = page.locator('.accion-caja').first();
      if (await formCobro.count()) {
        await capturaConMargen(page, formCobro, 'cobro-efectivo', 12);
      }
    }

    await cargar(page, '/gestion.php?tab=stock');
    await captura(page, 'stock');

    // Cobrar el pedido recién creado en efectivo, para que reportes y caja
    // muestren movimientos reales.
    if (detalleUrl) {
      const destino = new URL(detalleUrl, BASE);
      await cargar(page, destino.pathname + destino.search);
      await page.selectOption('#metodo', 'EFECTIVO');
      const total = await page.locator('#recibido').getAttribute('data-total');
      const recibido = String(Number(total) + 1000);
      await page.fill('#recibido', recibido);
      await Promise.all([
        page.waitForNavigation({ timeout: 20000 }).catch(() => {}),
        page.click('form[data-form-pago] button[type="submit"]'),
      ]);
      await page.waitForLoadState('networkidle').catch(() => {});
      await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
      console.log('  pago confirmado en el pedido de demo');
    }

    await cargar(page, '/reportes.php');
    await captura(page, 'reportes');

    await cargar(page, '/gestion.php?tab=caja');
    await captura(page, 'caja');
    const formCierre = page.locator('form.formulario-cierre').first();
    if (await formCierre.count()) {
      await capturaConMargen(page, page.locator('section.panel:has(form.formulario-cierre)').first(), 'cierre-caja', 10);
    }

    await cargar(page, '/admin.php?tab=usuarios');
    await captura(page, 'admin-usuarios');

    await cargar(page, '/admin.php?tab=productos');
    await captura(page, 'admin-productos');
    const editar = page.locator('a[href*="editar="]').first();
    if (await editar.count()) {
      await Promise.all([
        page.waitForURL(/editar=/, { timeout: 15000 }),
        editar.click(),
      ]);
      await page.waitForLoadState('networkidle').catch(() => {});
      await page.addStyleTag({ content: sinAnimacion }).catch(() => {});
      await bajarA(page, '#p-nombre');
      await captura(page, 'admin-producto-imagen');
    }

    await cargar(page, '/admin.php?tab=configuracion');
    await captura(page, 'admin-config');

    await cargar(page, '/admin.php?tab=auditoria');
    await captura(page, 'auditoria');

    await cargar(page, '/anexos.php');
    await captura(page, 'anexos');

    await cargar(page, '/video.php');
    await captura(page, 'video');

    await ctx.close();
  }
} finally {
  await browser.close();
}

console.log('Capturas terminadas en', OUT);
