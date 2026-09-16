import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('director', () => {
  test('director can login and reach dashboard', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.director, fx.password);
    await page.waitForURL(/\/director\/dashboard/, { timeout: 30_000 });
    await expect(page.locator('body')).toContainText(/Gestión|Director|colegio/i);
  });

  test('director is blocked from teacher hub', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.director, fx.password);
    await page.waitForURL(/\/director/, { timeout: 30_000 });
    await page.goto('/teacher/hub', { waitUntil: 'load' });
    await expect(page).not.toHaveURL(/\/teacher\/hub$/);
  });
});
