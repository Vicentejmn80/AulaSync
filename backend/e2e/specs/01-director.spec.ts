import { expect, test } from '@playwright/test';
import { loadManifest } from '../helpers/accounts';
import { attachMonitor, assertNoFrontendCrash, login } from '../helpers/login';

const accounts = loadManifest();

test.describe('Director', () => {
  test('login and dashboard', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, accounts.director.email, accounts.password, '/director/dashboard');
    await expect(page.getByText('AulaSync QA School')).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(monitor);
  });

  test('gestion lists teachers students and courses', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, accounts.director.email, accounts.password, '/director/dashboard');
    await page.goto('/director/gestion');
    await expect(page.getByRole('heading', { name: 'Gestión' })).toBeVisible({ timeout: 20000 });

    await page.getByRole('button', { name: /Plantel/ }).click();
    await expect(page.getByRole('heading', { name: 'Plantel docente' })).toBeVisible();
    await expect(page.locator('table.hub-table').getByText('Docente QA 01', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: /Nómina/ }).click();
    await expect(page.getByRole('heading', { name: 'Alumnos' })).toBeVisible();
    await expect(page.locator('table.hub-table').getByText('Alumno QA 01', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: /Oferta/ }).click();
    await expect(page.getByRole('heading', { name: 'Oferta por grado' })).toBeVisible();
    await expect(page.locator('.grade-chip').filter({ hasText: /Matemática/i }).first()).toBeVisible();
    assertNoFrontendCrash(monitor);
  });

  test('create teacher via gestion hub', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, accounts.director.email, accounts.password, '/director/dashboard');
    await page.goto('/director/gestion?panel=teachers');
    await expect(page.getByRole('heading', { name: 'Plantel docente' })).toBeVisible({ timeout: 20000 });
    await page.getByRole('button', { name: /Invitar profesor/ }).click();
    await expect(page.getByRole('heading', { name: 'Nuevo profesor' })).toBeVisible({ timeout: 15000 });
    await page.getByPlaceholder('Nombre del docente').fill('Docente QA Playwright');
    await page.getByPlaceholder(/Correo/).fill('docente.qa.playwright@qa.aulasync.test');
    await page.getByRole('button', { name: /Crear profesor/ }).click();
    await expect(page.getByText(/Docente QA Playwright|creado|invit/i).first()).toBeVisible({ timeout: 20000 });
    assertNoFrontendCrash(monitor);
  });

  test('director AI query', async ({ page }) => {
    const monitor = attachMonitor(page);
    await login(page, accounts.director.email, accounts.password, '/director/dashboard');
    const trigger = page.locator('button.nova-ai-trigger');
    if (await trigger.count() === 0) {
      test.info().annotations.push({ type: 'skip', description: 'AI bubble not on dashboard' });
      return;
    }
    await trigger.click();
    await page.locator('.nova-ai-input textarea').fill('¿Cuántos alumnos hay en el colegio?');
    await page.locator('.send-btn').click();
    const assistant = page.locator('.message-assistant').last();
    await expect(assistant).toBeVisible({ timeout: 45000 });
    const text = (await assistant.innerText()).trim();
    expect(text.length).toBeGreaterThan(8);
    expect(text).not.toContain('Ocurrió un error al preparar');
    assertNoFrontendCrash(monitor);
  });
});
