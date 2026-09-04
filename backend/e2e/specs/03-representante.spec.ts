import { expect, test } from '@playwright/test';
import { loadManifest } from '../helpers/accounts';
import { attachMonitor, assertNoFrontendCrash, familyCalendarChips, login, openFamilyCalendar } from '../helpers/login';

const accounts = loadManifest();
const parent = accounts.parents[0];

test.describe('Representative', () => {
  test('login sees authorized children only', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, parent.email, accounts.password, '/representante/dashboard');
    await expect(page.getByRole('button', { name: 'Alumno QA 01', exact: true })).toBeVisible({ timeout: 20000 });
    await expect(page.getByText('Alumno QA Other')).toHaveCount(0);
    assertNoFrontendCrash(monitor);
  });

  test('calendar and contextual activity explanation', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, parent.email, accounts.password, '/representante/dashboard');
    await openFamilyCalendar(page);
    await expect(page.getByRole('heading', { name: /Calendario académico/i })).toBeVisible({ timeout: 20000 });
    await expect(page.getByRole('button', { name: /Resume esta semana/i })).toBeVisible();
    await expect(page.locator('.calendar-stats')).toBeVisible();
    // Events load via refreshAll after paint; wait for the month payload, not a sleep.
    await expect(page.locator('.calendar-stats')).not.toHaveText(/^0 eventos$/i, { timeout: 20000 });
    const tarea = familyCalendarChips(page, /Tarea QA Matemática 1ro/);
    await expect(tarea.first()).toBeVisible({ timeout: 15000 });
    await tarea.first().click();
    await expect(page.getByRole('button', { name: 'Explícame esta actividad' }).first()).toBeVisible({ timeout: 15000 });
    assertNoFrontendCrash(monitor);
  });

  test('calendar shows seeded task for enrolled child', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, parent.email, accounts.password, '/representante/dashboard');
    await openFamilyCalendar(page);
    await expect(page.locator('.calendar-stats')).not.toHaveText(/^0 eventos$/i, { timeout: 20000 });
    await expect(familyCalendarChips(page, /Tarea QA Matemática 1ro/).first()).toBeVisible({ timeout: 15000 });
    assertNoFrontendCrash(monitor);
  });

  test('weekly summary grades and attendance buttons exist', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, parent.email, accounts.password, '/representante/dashboard');
    await expect(page.getByRole('button', { name: /Explícame su progreso/i }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /Explícame su asistencia/i }).first()).toBeVisible();
    await openFamilyCalendar(page);
    await expect(page.getByRole('button', { name: /Resume esta semana/i })).toBeVisible();
    assertNoFrontendCrash(monitor);
  });
});
