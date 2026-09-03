import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('docentes', () => {
  test('teacher hub page.goto after login', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.teacher, fx.password);
    await page.waitForURL(/\/teacher\/hub/, { timeout: 30_000 });
    await page.goto('/teacher/hub', { waitUntil: 'load' });
    await expect(page).toHaveURL(/\/teacher\/hub/);
    await expect(page.locator('body')).toContainText(/AulaSync|Hub|Académico/i);
  });

  test('create activity from form', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.teacher, fx.password);
    await page.waitForURL(/\/teacher\/hub/, { timeout: 30_000 });
    await page.goto('/teacher/activities', { waitUntil: 'load' });
    await page.getByRole('link', { name: /Crear actividad/i }).first().click().catch(async () => {
      await page.getByText('Crear actividad', { exact: false }).first().click();
    });
    await expect(page.locator('h3', { hasText: 'Crear actividad' })).toBeVisible({ timeout: 10_000 });
    await page.locator('select[name="course_id"]').selectOption({ index: 1 });
    await page.locator('input[name="type"][value="actividad"]').check();
    const title = `Actividad E2E ${Date.now()}`;
    await page.locator('input[name="title"]').fill(title);
    await page.locator('#is_homework').check();
    const due = new Date();
    const iso = due.toISOString().slice(0, 10);
    await page.locator('input[name="due_date"]').fill(iso);
    await page.locator('form[action*="activities"] button[type="submit"]').click();
    await page.waitForLoadState('load');
    await expect(page.locator('body')).toContainText(new RegExp(title));
  });

  test('teacher calendar lists own course activity', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.teacher, fx.password);
    await page.waitForURL(/\/teacher\/hub/, { timeout: 30_000 });
    const res = await page.request.get('/teacher/api/calendar');
    expect(res.ok()).toBeTruthy();
    const json = await res.json();
    const blob = JSON.stringify(json);
    expect(blob).toContain(fx.seededTask);
  });
});
