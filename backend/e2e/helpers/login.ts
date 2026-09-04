import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test';

export type SessionMonitor = {
  consoleErrors: string[];
  httpFailures: { url: string; status: number }[];
};

const EXPECTED_STATUSES = new Set([401, 403, 404, 422]);

export function attachMonitor(page: Page): SessionMonitor {
  const monitor: SessionMonitor = { consoleErrors: [], httpFailures: [] };

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      const text = msg.text();
      if (text.includes('favicon') || text.includes('cdn.') || text.includes('Failed to load resource')) {
        return;
      }
      monitor.consoleErrors.push(text);
    }
  });

  page.on('pageerror', (err) => {
    monitor.consoleErrors.push(err.message);
  });

  page.on('response', (res) => {
    const status = res.status();
    if (status < 400) {
      return;
    }
    if (EXPECTED_STATUSES.has(status) && res.url().includes('/api/')) {
      return;
    }
    if (status >= 500 || status === 419 || status === 409 || status === 429) {
      monitor.httpFailures.push({ url: res.url(), status });
    }
  });

  return monitor;
}

/** Sidebar nav in the family hub. Home also has a "Ver calendario" button that matches /Calendario/i. */
export async function openFamilyCalendar(page: Page): Promise<void> {
  await page.getByRole('complementary').getByRole('button', { name: /Calendario/ }).click();
}

/** Month-grid chips. Home's week strip keeps the same titles in hidden DOM via x-show. */
export function familyCalendarChips(page: Page, name: RegExp | string) {
  return page.locator('.calendar-days .cal-grade-event-title').filter({ hasText: name });
}

export async function login(page: Page, email: string, password: string, expectedPath: string): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.btn-submit').click();
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20000 });
  await expect(page).toHaveURL(new RegExp(expectedPath.replace('/', '\\/')));
  const body = await page.locator('body').innerText();
  expect(body.trim().length).toBeGreaterThan(40);
}

export async function openContext(
  browser: Browser,
  email: string,
  password: string,
  expectedPath: string,
): Promise<{ context: BrowserContext; page: Page; monitor: SessionMonitor }> {
  const context = await browser.newContext();
  const page = await context.newPage();
  const monitor = attachMonitor(page);
  await login(page, email, password, expectedPath);
  return { context, page, monitor };
}

export function assertNoFrontendCrash(monitor: SessionMonitor): void {
  const fatal = monitor.consoleErrors.filter((e) => /TypeError|ReferenceError|SyntaxError/i.test(e));
  expect(fatal, fatal.join('\n')).toEqual([]);
  const unexpectedHttp = monitor.httpFailures.filter((h) => h.status >= 500 || h.status === 419);
  expect(unexpectedHttp, JSON.stringify(unexpectedHttp)).toEqual([]);
}
