// @ts-check
const { test, expect } = require('@playwright/test');

async function iniciarSesion(page, correo, password) {
    await page.goto('/login.php');
    await page.fill('#correo', correo);
    await page.fill('#password', password);
    await page.getByRole('button', { name: 'Entrar' }).click();
}

test('el administrador entra a la gestión', async ({ page }) => {
    await iniciarSesion(page, 'vegaperezkevinsantiago@gmail.com', 'Admin123!');

    await expect(page).toHaveURL(/gestion\.php/);
    await expect(page.getByRole('heading', { name: 'Gestión' })).toBeVisible();
    await expect(page.locator('.tabs').getByRole('link', { name: 'Pedidos', exact: true })).toBeVisible();
    await expect(page.locator('.tabs').getByRole('link', { name: 'Caja', exact: true })).toBeVisible();
});

test('el encargado accede a gestión pero no a administración', async ({ page }) => {
    await iniciarSesion(page, 'encargado@coffeeandtea.local', 'Encargado123!');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/admin.php');
    await expect(page).toHaveURL(/index\.php/);
});

test('el cliente no accede a gestión ni administración', async ({ page }) => {
    await iniciarSesion(page, 'cliente@coffeeandtea.local', 'Cliente123!');
    await expect(page).toHaveURL(/cuenta\.php/);

    await page.goto('/gestion.php');
    await expect(page).toHaveURL(/index\.php/);

    await page.goto('/admin.php');
    await expect(page).toHaveURL(/index\.php/);
});

test('el visitante es enviado al login desde gestión', async ({ page }) => {
    await page.goto('/gestion.php');
    await expect(page).toHaveURL(/login\.php/);
});

test('las credenciales incorrectas muestran un mensaje genérico', async ({ page }) => {
    await iniciarSesion(page, 'nadie@example.com', 'ClaveIncorrecta1!');
    await expect(page.locator('body')).toContainText('Correo o contraseña incorrectos');
});

test('el encargado no usa carrito ni cuenta de cliente', async ({ page }) => {
    await iniciarSesion(page, 'encargado@coffeeandtea.local', 'Encargado123!');
    await expect(page).toHaveURL(/gestion\.php/);

    await expect(page.locator('.menu')).not.toContainText('Carrito');
    await expect(page.locator('.menu')).not.toContainText('Mi cuenta');

    await page.goto('/carrito.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/checkout.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/cuenta.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/index.php');
    await expect(page.locator('.form-agregar')).toHaveCount(0);
    await expect(page.locator('.hero-acciones')).toContainText('Ir a gestión');
});

test('el administrador no usa carrito ni cuenta de cliente', async ({ page }) => {
    await iniciarSesion(page, 'vegaperezkevinsantiago@gmail.com', 'Admin123!');
    await expect(page).toHaveURL(/gestion\.php/);

    await expect(page.locator('.menu')).not.toContainText('Carrito');
    await expect(page.locator('.menu')).not.toContainText('Mi cuenta');

    await page.goto('/carrito.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/checkout.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/cuenta.php');
    await expect(page).toHaveURL(/gestion\.php/);

    await page.goto('/index.php');
    await expect(page.locator('.form-agregar')).toHaveCount(0);
    await expect(page.locator('.hero-acciones')).toContainText('Ir a gestión');
});
