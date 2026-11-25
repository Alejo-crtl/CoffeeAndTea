// @ts-check
const { test, expect } = require('@playwright/test');

async function sinDesborde(page, nombre) {
    const diferencia = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(diferencia, `desborde horizontal en ${nombre}`).toBeLessThanOrEqual(1);
}

async function agregarAlCarrito(page) {
    await page.goto('/');
    await page.locator('.tarjeta-producto .form-agregar button').first().click();
    await page.waitForLoadState('networkidle');
}

test('la tienda no desborda en móvil', async ({ page }) => {
    await page.goto('/');
    await sinDesborde(page, 'tienda');
});

test('el manual no desborda en móvil', async ({ page }) => {
    await page.goto('/manual.php');
    await sinDesborde(page, 'manual');
});

test('anexos y video no desbordan en móvil', async ({ page }) => {
    await page.goto('/anexos.php');
    await sinDesborde(page, 'anexos');
    await page.goto('/video.php');
    await sinDesborde(page, 'video');
});

test('el carrito y el checkout no desbordan en móvil', async ({ page }) => {
    await agregarAlCarrito(page);
    await page.goto('/carrito.php');
    await sinDesborde(page, 'carrito');
    await page.goto('/checkout.php');
    await sinDesborde(page, 'checkout');
});

test('la gestión no desborda en móvil', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('#correo', 'admin@coffeeandtea.local');
    await page.fill('#password', 'Admin123!');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/gestion\.php/);
    for (const tab of ['pedidos', 'caja', 'stock']) {
        await page.goto(`/gestion.php?tab=${tab}`);
        await sinDesborde(page, `gestión ${tab}`);
    }
});
