// @ts-check
const { test, expect } = require('@playwright/test');

test('la tienda muestra catálogo, stock y tiempo estimado', async ({ page }) => {
    await page.goto('/index.php');

    await expect(page).toHaveTitle(/CoffeeAndtea/i);
    await expect(page.locator('.hero-datos')).toContainText('Tiempo estimado');
    await expect(page.locator('.hero-datos')).toContainText('Solo recogida');
    await expect(page.locator('.menu-tiempo')).toContainText('min');
    await expect(page.locator('.tarjeta-producto').first()).toBeVisible();
    await expect(page.locator('.tarjeta-producto .producto-precio').first()).toContainText('$');
});

test('un visitante puede pedir para recoger en tienda', async ({ page }) => {
    const sufijo = Date.now().toString().slice(-6);

    await page.goto('/index.php');
    await page.locator('.tarjeta-producto .form-agregar button').first().click();
    await expect(page.locator('body')).toContainText('Producto agregado al carrito');

    await page.goto('/carrito.php');
    await expect(page.getByRole('heading', { name: 'Carrito' })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Cancelación: 100%');
    await page.getByRole('link', { name: 'Continuar con el pedido' }).click();

    await expect(page.locator('#dialogo-invitado')).toBeVisible();
    await expect(page.locator('#dialogo-invitado')).toContainText('Guardaremos este pedido');
    await expect(page.getByRole('button', { name: 'Crear cuenta' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Entendido, continuar' }).click();

    await expect(page).toHaveURL(/checkout\.php/);
    await expect(page.locator('body')).not.toContainText('Si cancelas antes de que');
    await expect(page.locator('form a[href$="politica-cancelacion.php"]')).toBeVisible();
    await page.fill('#nombre', 'Cliente E2E ' + sufijo);
    await page.fill('#correo', `e2e.${sufijo}@example.com`);
    await page.fill('#telefono', '3001234567');
    await page.fill('#observaciones', 'Pedido creado por Playwright');
    await page.check('input[name="consentimiento"]');
    await page.check('input[name="acepta_cancelacion"]');
    await page.getByRole('button', { name: 'Registrar pedido' }).click();

    await expect(page).toHaveURL(/pedido\.php\?codigo=/);
    await expect(page.getByRole('heading', { name: 'Pedido registrado' })).toBeVisible();
    await expect(page.locator('body')).toContainText('Tiempo estimado');
    await expect(page.locator('body')).toContainText('mostrador');
    await expect(page.locator('body')).toContainText('También guardamos este pedido');

    await page.getByRole('link', { name: 'Mis pedidos recientes' }).last().click();
    await expect(page).toHaveURL(/pedidos-invitado\.php/);
    await expect(page.getByRole('heading', { name: 'Pedidos recientes' })).toBeVisible();
    await expect(page.locator('table')).toContainText('CAF-');

    await page.getByRole('link', { name: 'Ver detalle' }).first().click();
    await expect(page).toHaveURL(/pedido\.php\?codigo=/);
    await expect(page.locator('.menu a.activo')).toHaveText('Mis pedidos');
});

test('el checkout exige aceptar el tratamiento de datos', async ({ page }) => {
    await page.goto('/index.php');
    await page.locator('.tarjeta-producto .form-agregar button').first().click();
    await expect(page.locator('body')).toContainText('Producto agregado al carrito');
    await page.goto('/carrito.php');
    await page.getByRole('link', { name: 'Continuar con el pedido' }).click();
    await page.getByRole('button', { name: 'Entendido, continuar' }).click();

    await page.fill('#nombre', 'Cliente sin consentimiento');
    await page.fill('#correo', 'sin.consentimiento@example.com');
    await page.getByRole('button', { name: 'Registrar pedido' }).click();

    await expect(page).toHaveURL(/checkout\.php/);
    await expect(page.locator('input[name="consentimiento"]')).toHaveAttribute('required', '');
});
