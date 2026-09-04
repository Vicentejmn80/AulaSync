import fs from 'node:fs';
import path from 'node:path';
import type { FullConfig, FullResult, Reporter, TestCase, TestResult } from '@playwright/test/reporter';

type Row = {
  role: string;
  workflow: string;
  step: string;
  status: 'PASS' | 'FAIL' | 'SKIP';
  error?: string;
  evidence?: string;
};

function roleFromTitle(title: string, file: string): string {
  const lower = `${file} ${title}`.toLowerCase();
  if (lower.includes('director')) return 'Director';
  if (lower.includes('teacher') || lower.includes('docente')) return 'Teachers';
  if (lower.includes('parent') || lower.includes('representante')) return 'Representatives';
  if (lower.includes('concurr')) return 'Concurrency';
  if (lower.includes('cross') || lower.includes('isolat')) return 'Cross-role';
  return 'Other';
}

class AulaQaReporter implements Reporter {
  private rows: Row[] = [];

  onTestEnd(test: TestCase, result: TestResult): void {
    const status = result.status === 'passed' ? 'PASS' : result.status === 'skipped' ? 'SKIP' : 'FAIL';
    const shot = result.attachments.find((a) => a.name === 'screenshot' || a.contentType?.includes('image'));
    this.rows.push({
      role: roleFromTitle(test.title, test.location.file),
      workflow: test.parent.title || test.title,
      step: test.title,
      status,
      error: result.error?.message?.split('\n')[0],
      evidence: shot?.path || result.attachments.find((a) => a.name === 'trace')?.path,
    });
  }

  onEnd(result: FullResult): void {
    const dir = path.resolve(process.cwd(), 'tests/evidence');
    fs.mkdirSync(dir, { recursive: true });
    const phpunit = path.resolve(process.cwd(), 'storage/app/qa/phpunit-testdox.txt');
    const phpunitText = fs.existsSync(phpunit) ? fs.readFileSync(phpunit, 'utf8') : '';

    const passed = this.rows.filter((r) => r.status === 'PASS').length;
    const failed = this.rows.filter((r) => r.status === 'FAIL').length;
    const skipped = this.rows.filter((r) => r.status === 'SKIP').length;

    const groups = new Map<string, Row[]>();
    for (const row of this.rows) {
      const list = groups.get(row.role) || [];
      list.push(row);
      groups.set(row.role, list);
    }

    const lines = [
      'AULASYNC QA REPORT',
      '',
      'Environment: QA School',
      `Playwright status: ${result.status}`,
      '',
    ];

    if (phpunitText.trim()) {
      lines.push('PHPUnit:', phpunitText.trim(), '');
    }

    for (const [role, rows] of groups) {
      lines.push(`${role}:`);
      for (const row of rows) {
        lines.push(`${row.status} — ${row.step}`);
        if (row.status === 'FAIL') {
          lines.push(`  Role: ${role}`);
          lines.push(`  Workflow: ${row.workflow}`);
          lines.push(`  Step: ${row.step}`);
          lines.push(`  Error: ${row.error || 'unknown'}`);
          lines.push(`  Evidence: ${row.evidence || 'tests/evidence'}`);
        }
      }
      lines.push('');
    }

    lines.push('TOTAL:');
    lines.push(`${passed} passed`);
    lines.push(`${failed} failed`);
    lines.push(`${skipped} skipped`);
    lines.push('');

    const text = lines.join('\n');
    fs.writeFileSync(path.join(dir, 'AULASYNC-QA-REPORT.txt'), text);
    fs.mkdirSync(path.resolve(process.cwd(), 'storage/app/qa'), { recursive: true });
    fs.writeFileSync(path.resolve(process.cwd(), 'storage/app/qa/AULASYNC-QA-REPORT.txt'), text);
    fs.writeFileSync(path.resolve(process.cwd(), 'storage/app/qa/playwright-results.json'), JSON.stringify(this.rows, null, 2));
  }

  onBegin(_config: FullConfig): void {}
}

export default AulaQaReporter;
