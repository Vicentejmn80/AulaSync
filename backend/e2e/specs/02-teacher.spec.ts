import { expect, test } from '@playwright/test';
import { loadManifest } from '../helpers/accounts';
import { attachMonitor, assertNoFrontendCrash, login } from '../helpers/login';

const accounts = loadManifest();
const teacher = accounts.teachers[0];

test.describe('Teacher', () => {
  test('login hub courses and students', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, teacher.email, accounts.password, '/teacher/hub');
    await expect(page.locator('#hub-root')).toBeVisible();
    await page.goto('/teacher/courses');
    await expect(page.getByText(/Matemática|curso/i).first()).toBeVisible({ timeout: 20000 });
    await page.goto('/teacher/activities');
    await expect(page.getByText(/Actividad|Tarea QA/i).first()).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(monitor);
  });

  test('create activity from form', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, teacher.email, accounts.password, '/teacher/hub');
    await page.goto('/teacher/activities');
    await page.getByRole('button', { name: /Nueva/ }).click();
    await expect(page.getByRole('heading', { name: 'Crear actividad' })).toBeVisible({ timeout: 15000 });
    const courseSelect = page.locator('select[name="course_id"]');
    await expect(courseSelect).toBeVisible();
    await courseSelect.selectOption({ index: 1 });
    await page.locator('input[name="title"]').fill('Tarea QA Playwright');
    const desc = page.locator('textarea[name="description"]');
    if (await desc.count()) {
      await desc.fill('Creada desde Playwright.');
    }
    await page.getByRole('button', { name: /Crear Actividad|Crear Clase/ }).click();
    await expect(page.getByText('Tarea QA Playwright').first()).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(monitor);
  });

  test('attendance page loads roster', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, teacher.email, accounts.password, '/teacher/hub');
    await page.goto('/teacher/attendance');
    await expect(page.locator('select').first()).toBeVisible({ timeout: 20000 });
    await expect(page.getByText(/Alumno QA|asistencia|presente/i).first()).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(monitor);
  });
});
