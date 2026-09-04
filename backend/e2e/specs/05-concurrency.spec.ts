import { expect, test } from '@playwright/test';
import { loadManifest } from '../helpers/accounts';
import { openContext, assertNoFrontendCrash } from '../helpers/login';

const accounts = loadManifest();

test.describe('Concurrency', () => {
  test('five teachers act in independent sessions at once', async ({ browser }) => {
    const sessions = await Promise.all(
      accounts.teachers.map((teacher) => openContext(browser, teacher.email, accounts.password, '/teacher/hub')),
    );

    await Promise.all(sessions.map(async (session, i) => {
      await session.page.goto('/teacher/activities');
      await expect(session.page.locator('select[name="course_id"], body')).toBeVisible({ timeout: 25000 });
      const body = await session.page.locator('body').innerText();
      expect(body).toMatch(/Actividad|Tarea QA|curso/i);
      expect(body).not.toContain(accounts.teachers.filter((_, j) => j !== i)[0]?.email || '___none___');
      assertNoFrontendCrash(session.monitor);
    }));

    for (const session of sessions) {
      await session.context.close();
    }
  });

  test('ten parents query dashboards concurrently without mixing sessions', async ({ browser }) => {
    const parents = accounts.parents.slice(0, 10);
    const sessions = await Promise.all(
      parents.map((parent) => openContext(browser, parent.email, accounts.password, '/representante/dashboard')),
    );

    await Promise.all(sessions.map(async (session, i) => {
      const own = `Alumno QA ${String(i * 2 + 1).padStart(2, '0')}`;
      await expect(session.page.getByText(own)).toBeVisible({ timeout: 20000 });
      await expect(session.page.getByText('Alumno QA Other')).toHaveCount(0);
      const body = await session.page.locator('body').innerText();
      for (const [j, other] of parents.entries()) {
        if (j === i) continue;
        expect(body).not.toContain(other.email);
      }
      assertNoFrontendCrash(session.monitor);
    }));

    for (const session of sessions) {
      await session.context.close();
    }
  });
});
