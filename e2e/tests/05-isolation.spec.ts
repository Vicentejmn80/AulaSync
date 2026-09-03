import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('isolation', () => {
  test('family A does not see school B child or task', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.parent, fx.password);
    await page.waitForURL(/representante/, { timeout: 30_000 });
    await expect(page.locator('body')).toContainText(fx.childA, { timeout: 20_000 });
    await expect(page.locator('body')).not.toContainText(fx.childB);
    await expect(page.locator('body')).not.toContainText(fx.foreignTask);
  });

  test('teacher B calendar does not include school A task', async ({ page }) => {
    const fx = fixtures();
    await login(page, fx.teacherB, fx.password);
    await page.waitForURL(/\/teacher\/hub/, { timeout: 30_000 });
    const res = await page.request.get('/teacher/api/calendar');
    expect(res.ok()).toBeTruthy();
    const blob = JSON.stringify(await res.json());
    expect(blob).not.toContain(fx.seededTask);
    expect(blob).toContain(fx.foreignTask);
  });
});
