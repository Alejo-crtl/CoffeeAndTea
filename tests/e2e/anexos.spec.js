// @ts-check
const { test, expect } = require('@playwright/test');

test('la página de anexos lista y descarga los documentos', async ({ page, request }) => {
    await page.goto('/anexos.php');
    await expect(page.getByRole('heading', { name: 'Anexos' })).toBeVisible();
    await expect(page.locator('.anexo-card')).toHaveCount(6);

    const enlaces = page.locator('.anexo-card a.btn-primario');
    await expect(enlaces).toHaveCount(6);

    for (let i = 0; i < 6; i++) {
        const href = await enlaces.nth(i).getAttribute('href');
        expect(href).toBeTruthy();
        const respuesta = await request.get(href);
        expect(respuesta.status()).toBe(200);
        expect(respuesta.headers()['content-disposition']).toContain('attachment');
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/anexos.php');
    const desborde = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(desborde).toBeLessThanOrEqual(1);
});

test('el footer enlaza a anexos desde la tienda', async ({ page }) => {
    await page.goto('/index.php');
    const enlace = page.locator('.site-footer a', { hasText: 'Anexos' });
    await expect(enlace).toBeVisible();
    await enlace.click();
    await expect(page).toHaveURL(/anexos\.php/);
    await expect(page.getByRole('heading', { name: 'Anexos' })).toBeVisible();
});
