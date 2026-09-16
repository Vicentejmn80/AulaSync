import { test, expect } from '@playwright/test';
import { fixtures, login } from '../helpers/auth';

test.describe('concurrency', () => {
  test('overlapping login page.goto for teacher and family', async ({ browser }) => {
    const fx = fixtures();
    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    const pageA = await ctxA.newPage();
    const pageB = await ctxB.newPage();
    const results = await Promise.allSettled([
      pageA.goto('/login', { waitUntil: 'load', timeout: 30_000 }),
      pageB.goto('/login', { waitUntil: 'load', timeout: 30_000 }),
    ]);
    await ctxA.close();
    await ctxB.close();
    expect(results.filter((r) => r.status === 'rejected')).toHaveLength(0);
  });

  test('teacher and family can login concurrently and reach their hubs', async ({ browser }) => {
    const fx = fixtures();
    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    const teacherPage = await ctxA.newPage();
    const familyPage = await ctxB.newPage();
    await Promise.all([
      login(teacherPage, fx.teacher, fx.password),
      login(familyPage, fx.parent, fx.password),
    ]);
    await Promise.all([
      teacherPage.waitForURL(/\/teacher\/hub/, { timeout: 30_000 }),
      familyPage.waitForURL(/representante/, { timeout: 30_000 }),
    ]);
    await expect(teacherPage).toHaveURL(/\/teacher\/hub/);
    await expect(familyPage).toHaveURL(/representante/);
    await ctxA.close();
    await ctxB.close();
  });
});
