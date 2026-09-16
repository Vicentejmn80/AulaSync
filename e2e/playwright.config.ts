import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const root = path.resolve(__dirname, '..');
const backend = path.join(root, 'backend');
const baseURL = process.env.BASE_URL || 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: path.join(__dirname, 'tests'),
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 45_000,
  reporter: [['list']],
  globalSetup: path.join(__dirname, 'global-setup.ts'),
  use: {
    baseURL,
    channel: 'chrome',
    headless: true,
    navigationTimeout: 30_000,
    actionTimeout: 15_000,
    trace: 'off',
  },
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    cwd: backend,
    url: `${baseURL}/login`,
    reuseExistingServer: true,
    timeout: 60_000,
  },
  projects: [
    {
      name: 'chrome',
      use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    },
  ],
});
