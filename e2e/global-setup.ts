import { execSync } from 'node:child_process';
import path from 'node:path';

export default async function globalSetup(): Promise<void> {
  const root = path.resolve(__dirname, '..');
  execSync('php e2e/seed.php', { cwd: root, stdio: 'inherit' });
}
