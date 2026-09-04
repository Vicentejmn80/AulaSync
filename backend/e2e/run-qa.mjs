import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function run(command, args, extra = {}) {
  console.log(`\n> ${command} ${args.join(' ')}\n`);
  const result = spawnSync(command, args, {
    cwd: root,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    ...extra,
  });
  return result.status === 0;
}

const skipReset = process.argv.includes('--skip-reset');
const skipPhpunit = process.argv.includes('--skip-phpunit');
const skipE2e = process.argv.includes('--skip-e2e');

fs.mkdirSync(path.join(root, 'storage', 'app', 'qa'), { recursive: true });
fs.mkdirSync(path.join(root, 'tests', 'evidence'), { recursive: true });

let ok = true;
if (!skipReset) {
  ok = run('php', ['artisan', 'demo:reset']) && ok;
}
if (!skipPhpunit) {
  ok = run('php', [
    'artisan',
    'test',
    '--testsuite=Qa',
    '--testdox',
    `--testdox-text=${path.join('storage', 'app', 'qa', 'phpunit-testdox.txt')}`,
  ]) && ok;
}
if (!skipE2e) {
  ok = run('npx', ['playwright', 'test', '--config=e2e/playwright.config.ts']) && ok;
}

const report = path.join(root, 'tests', 'evidence', 'AULASYNC-QA-REPORT.txt');
if (fs.existsSync(report)) {
  console.log('\n===== AULASYNC QA REPORT =====\n');
  console.log(fs.readFileSync(report, 'utf8'));
}

process.exit(ok ? 0 : 1);
