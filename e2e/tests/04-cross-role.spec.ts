import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('cross-role', () => {
  test('teacher cannot open family hub', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.teacher, fx.password);
    await page.waitForURL(/\/teacher\/hub/, { timeout: 30_000 });
    await page.goto('/representante/dashboard', { waitUntil: 'load' });
    await expect(page).not.toHaveURL(/\/representante\/dashboard$/);
  });

  test('family cannot open teacher hub', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.parent, fx.password);
    await page.waitForURL(/representante/, { timeout: 30_000 });
    await page.goto('/teacher/hub', { waitUntil: 'load' });
    await expect(page).not.toHaveURL(/\/teacher\/hub$/);
  });
});
