import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('representantes', () => {
  test('family hub page.goto after login', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.parent, fx.password);
    await page.waitForURL(/representante/, { timeout: 30_000 });
    await page.goto('/representante/dashboard', { waitUntil: 'load' });
    await expect(page).toHaveURL(/representante/);
    await expect(page.locator('body')).toContainText(/Calendario académico|Familia|AulaSync/i);
  });

  test('calendar shows seeded task for enrolled child', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.parent, fx.password);
    await page.waitForURL(/representante/, { timeout: 30_000 });
    await expect(page.locator('body')).toContainText(fx.seededTask, { timeout: 20_000 });
    await expect(page.locator('body')).toContainText(fx.childA);
  });

  test('family summary exposes courses_count for enrolled child', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.parent, fx.password);
    await page.waitForURL(/representante/, { timeout: 30_000 });
    const studentId = await page.evaluate(() => {
      const select = document.querySelector<HTMLSelectElement>('select');
      return select?.value || '';
    });
    expect(studentId).not.toEqual('');
    const res = await page.request.get(`/representante/api/${studentId}/resumen`);
    expect(res.ok()).toBeTruthy();
    const json = await res.json();
    expect(Number(json.summary?.courses_count ?? 0)).toBeGreaterThanOrEqual(1);
  });
});
