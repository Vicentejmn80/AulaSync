import { expect, test } from '@playwright/test';
import { loadManifest } from '../helpers/accounts';
import { openContext, assertNoFrontendCrash, familyCalendarChips, openFamilyCalendar } from '../helpers/login';

const accounts = loadManifest();

test.describe('Cross-role and isolation', () => {
  test('teacher activity appears for authorized parent only', async ({ browser }) => {
    const teacher = await openContext(browser, accounts.teachers[0].email, accounts.password, '/teacher/hub');
    await teacher.page.goto('/teacher/activities');
    await expect(teacher.page.getByText(/Tarea QA Matemática 1ro|Tarea QA/i).first()).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(teacher.monitor);
    await teacher.context.close();

    const parent = await openContext(browser, accounts.parents[0].email, accounts.password, '/representante/dashboard');
    await openFamilyCalendar(parent.page);
    await expect(parent.page.locator('.calendar-stats')).not.toHaveText(/^0 eventos$/i, { timeout: 20000 });
    await expect(familyCalendarChips(parent.page, /Tarea QA Matemática 1ro/).first()).toBeVisible({ timeout: 15000 });
    assertNoFrontendCrash(parent.monitor);
    await parent.context.close();

    const other = await openContext(browser, accounts.other.parent.email, accounts.password, '/representante/dashboard');
    await expect(other.page.getByText('Alumno QA 01')).toHaveCount(0);
    await expect(other.page.getByRole('button', { name: 'Alumno QA Other', exact: true })).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(other.monitor);
    await other.context.close();
  });

  test('sessions stay isolated between director and parent', async ({ browser }) => {
    const director = await openContext(browser, accounts.director.email, accounts.password, '/director/dashboard');
    const parent = await openContext(browser, accounts.parents[0].email, accounts.password, '/representante/dashboard');

    await expect(director.page).toHaveURL(/director/);
    await expect(parent.page).toHaveURL(/representante/);
    await expect(director.page.getByText('Representante QA 01')).toHaveCount(0);
    await expect(parent.page.getByText('Director QA')).toHaveCount(0);
    await director.context.close();
    await parent.context.close();
  });
});
