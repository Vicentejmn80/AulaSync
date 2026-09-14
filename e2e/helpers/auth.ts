import { expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';

export type E2EFixtures = {
  director: string;
  teacher: string;
  parent: string;
  teacherB: string;
  parentB: string;
  password: string;
  seededTask: string;
  foreignTask: string;
  childA: string;
  childB: string;
  courseAId: number;
};

export function fixtures(): E2EFixtures {
  const file = path.resolve(__dirname, '..', 'fixtures.json');
  return JSON.parse(readFileSync(file, 'utf8'));
}

export async function login(page: Page, email: string, password = 'password'): Promise<void> {
  await page.goto('/login', { waitUntil: 'load' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.btn-submit').click();
}
