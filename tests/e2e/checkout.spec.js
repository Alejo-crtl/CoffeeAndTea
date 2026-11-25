// @ts-check
const { test, expect } = require('@playwright/test');

async function iniciarSesion(page, correo, password) {
    await page.goto('/login.php');
    await page.fill('#correo', correo);
    await page.fill('#password', password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).not.toHaveURL(/login\.php/);
}

async function irAlCheckout(page) {
    await page.goto('/index.php');
    await page.locator('.tarjeta-producto .form-agregar button').first().click();
    await page.waitForLoadState('networkidle');
    await page.goto('/carrito.php');
    await page.getByRole('link', { name: 'Continuar con el pedido' }).click();
    const dialogo = page.getByRole('button', { name: 'Entendido, continuar' });
    if (await dialogo.count()) {
        await dialogo.click();
    }
    await expect(page).toHaveURL(/checkout\.php/);
}

async function abrirPolitica(page, indice) {
    const enlaces = page.locator('[data-politica-enlace]');
    const [popup] = await Promise.all([
        page.context().waitForEvent('page'),
        enlaces.nth(indice).click(),
    ]);
    await popup.close();
}

test('un cliente con sesión no vuelve a autorizar sus datos', async ({ page }) => {
    await iniciarSesion(page, 'cliente@coffeeandtea.local', 'Cliente123!');
    await irAlCheckout(page);

    await expect(page.locator('input[name="consentimiento"]')).toHaveCount(0);
    await expect(page.locator('input[name="acepta_cancelacion"]')).toBeVisible();
});

test('la cancelación se habilita al abrir su política', async ({ page }) => {
    await irAlCheckout(page);

    const check = page.locator('input[name="acepta_cancelacion"]');
    await expect(check).toBeDisabled();
    await expect(page.locator('#nota-cancelacion')).toBeVisible();

    await abrirPolitica(page, 1);

    await expect(check).toBeEnabled();
    await expect(page.locator('#nota-cancelacion')).toBeHidden();
});

test('en el registro la casilla se habilita al abrir la política', async ({ page }) => {
    await page.goto('/registro.php');

    const check = page.locator('input[name="consentimiento"]');
    await expect(check).toBeDisabled();
    await expect(page.locator('#nota-consentimiento')).toBeVisible();

    await abrirPolitica(page, 0);

    await expect(check).toBeEnabled();
    await expect(page.locator('#nota-consentimiento')).toBeHidden();
});
