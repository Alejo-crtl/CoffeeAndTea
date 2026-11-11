// @ts-nocheck
// @ts-ignore Playwright is installed as a project dependency at runtime.
const { test, expect } = require('@playwright/test');

test('el pie enlaza al video de presentación', async (/** @type {{ page: import('@playwright/test').Page }} */ { page }) => {
    await page.goto('/index.php');

    const enlace = page.locator('.site-footer a', { hasText: 'Video' });
    await expect(enlace).toBeVisible();
    await enlace.click();

    await expect(page).toHaveURL(/video\.php/);
    await expect(page.getByRole('heading', { name: 'Video de presentación', level: 1 })).toBeVisible();

    const marco = page.locator('.video-marco');
    await expect(marco).toBeVisible();
    const iframe = await marco.locator('iframe').count();
    const placeholder = await marco.locator('.video-placeholder').count();
    expect(iframe + placeholder).toBe(1);
});

test('la página de video no desborda en móvil', async (/** @type {{ page: import('@playwright/test').Page }} */ { page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/video.php');

    await expect(page.locator('.video-marco')).toBeVisible();
    const desborde = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(desborde).toBeLessThanOrEqual(1);
});
