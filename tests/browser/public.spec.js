const { test, expect } = require('@playwright/test');

async function expectNoHorizontalOverflow(page) {
    const overflow = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        content: document.documentElement.scrollWidth
    }));
    expect(overflow.content).toBeLessThanOrEqual(overflow.viewport + 1);
}

async function expectElementNotCovered(locator) {
    const visibleAtCenter = await locator.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        const x = Math.min(window.innerWidth - 1, Math.max(0, rect.left + rect.width / 2));
        const y = Math.min(window.innerHeight - 1, Math.max(0, rect.top + rect.height / 2));
        const hit = document.elementFromPoint(x, y);
        return hit !== null && (hit === element || element.contains(hit));
    });
    expect(visibleAtCenter).toBe(true);
}

test('public home renders without horizontal overflow', async ({ page }) => {
    const response = await page.goto('/');
    expect(response).not.toBeNull();
    expect(response.status()).toBe(200);
    await expect(page.locator('main#main-content')).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('critical homepage sections keep their order, size and stacking', async ({ page }) => {
    await page.goto('/');

    // Главная DOUBLE A: hero с картой → полоса доверия (счётчики) → услуги.
    const hero = page.locator('.hero').first();
    const trust = page.locator('.trust-strip').first();
    const services = page.locator('#services .services-split').first();
    await expect(hero).toBeVisible();
    await expect(trust).toBeVisible();
    await expect(services).toBeVisible();

    const sectionGeometry = await page.evaluate(() => {
        const selectors = ['.hero', '.trust-strip', '#services .services-split'];
        return selectors.map((selector) => {
            const rect = document.querySelector(selector).getBoundingClientRect();
            return { top: rect.top + window.scrollY, bottom: rect.bottom + window.scrollY, width: rect.width };
        });
    });
    for (let i = 1; i < sectionGeometry.length; i += 1) {
        expect(sectionGeometry[i].top).toBeGreaterThan(sectionGeometry[i - 1].top);
    }
    const viewportWidth = await page.evaluate(() => document.documentElement.clientWidth + 1);
    for (const section of sectionGeometry) {
        expect(section.bottom).toBeGreaterThan(section.top);
        expect(section.width).toBeLessThanOrEqual(viewportWidth);
    }

    const card = page.locator('#services .service.quick').first();
    await card.scrollIntoViewIfNeeded();
    await expect(card).toBeVisible();
    await expectElementNotCovered(card);

    await expectNoHorizontalOverflow(page);
});

test('Uzbek home uses localized title and secure language URL', async ({ page }) => {
    const response = await page.goto('/uz');
    expect(response).not.toBeNull();
    expect(response.status()).toBe(200);
    await expect(page).toHaveTitle(/Bosh sahifa/);
    expect(page.url()).toMatch(/^http:\/\/127\.0\.0\.1:8080\/uz\/?$/);
});

test('selected language persists until the visitor explicitly changes it', async ({ page }) => {
    await page.goto('/uz');
    await page.goto('/projects');
    expect(page.url()).toMatch(/^http:\/\/127\.0\.0\.1:8080\/uz\/projects\/?$/);

    let cookies = await page.context().cookies();
    expect(cookies.find((cookie) => cookie.name === 'site_lang')?.value).toBe('uz');

    await page.goto('/projects?_lang=ru');
    expect(page.url()).toMatch(/^http:\/\/127\.0\.0\.1:8080\/projects\/?$/);
    cookies = await page.context().cookies();
    expect(cookies.find((cookie) => cookie.name === 'site_lang')?.value).toBe('ru');

    await page.goto('/uz/projects');
    expect(page.url()).toMatch(/^http:\/\/127\.0\.0\.1:8080\/projects\/?$/);
    cookies = await page.context().cookies();
    expect(cookies.find((cookie) => cookie.name === 'site_lang')?.value).toBe('ru');
});

test('mobile menu opens and closes accessibly', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'Mobile-only interaction');
    await page.goto('/');
    const burger = page.locator('#menuBtn');
    const nav = page.locator('#navlinks');
    await expect(burger).toBeVisible();
    await expect(nav).toBeHidden();

    await burger.click();
    await expect(nav).toHaveClass(/\bopen\b/);
    await expect(nav).toBeVisible();

    // Повторное нажатие бургера закрывает меню.
    await burger.click();
    await expect(nav).not.toHaveClass(/\bopen\b/);
    await expect(nav).toBeHidden();
});

test('health endpoint and admin login are reachable', async ({ page, request }) => {
    const health = await request.get('/health');
    expect(health.status()).toBe(200);
    const healthPayload = await health.json();
    expect(healthPayload.status).toMatch(/^(ok|degraded)$/);

    const response = await page.goto('/admin/login');
    expect(response).not.toBeNull();
    expect(response.status()).toBe(200);
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
});
