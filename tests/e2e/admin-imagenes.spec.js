// @ts-check
const { test, expect } = require('@playwright/test');

async function iniciarSesionAdmin(page) {
    await page.goto('/login.php');
    await page.fill('#correo', 'vegaperezkevinsantiago@gmail.com');
    await page.fill('#password', 'Admin123!');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/gestion\.php/);
}

test('el formulario de producto permite subir reemplazar y quitar la imagen', async ({ page }) => {
    await iniciarSesionAdmin(page);
    await page.goto('/admin.php?tab=productos');

    const campoImagen = page.locator('#p-imagen');
    await expect(campoImagen).toBeVisible();
    await expect(campoImagen).toHaveAttribute('accept', /image\/jpeg/);
    await expect(campoImagen).toHaveAttribute('accept', /image\/png/);
    await expect(campoImagen).toHaveAttribute('accept', /image\/webp/);

    const fila = page.locator('tr', { hasText: 'Café Express' });
    await fila.getByRole('link', { name: 'Editar' }).click();

    await expect(page.locator('.vista-previa-imagen img')).toBeVisible();
    await expect(page.locator('input[name="quitar_imagen"]')).toBeVisible();
    await expect(page.getByText('Sube otra imagen para reemplazar la actual')).toBeVisible();
});

test('la tienda muestra la imagen de cada tarjeta de producto', async ({ page }) => {
    await page.goto('/index.php#menu');

    const tarjetas = page.locator('.tarjeta-producto');
    await expect(tarjetas.first()).toBeVisible();
    const total = await tarjetas.count();
    const conImagen = await page.locator('.tarjeta-producto .producto-imagen img').count();

    expect(total).toBeGreaterThan(0);
    expect(conImagen).toBe(total);
});
