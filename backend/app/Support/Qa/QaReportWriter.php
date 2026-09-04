<?php

namespace App\Support\Qa;

use Illuminate\Support\Facades\File;

class QaReportWriter
{
    /**
     * @param  list<array{role:string,workflow:string,step:string,status:string,error?:string,evidence?:string}>  $results
     */
    public function write(array $results, string $environment = 'QA School'): string
    {
        $passed = count(array_filter($results, fn ($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($results, fn ($r) => $r['status'] === 'FAIL'));
        $skipped = count(array_filter($results, fn ($r) => $r['status'] === 'SKIP'));

        $lines = [
            'AULASYNC QA REPORT',
            '',
            'Environment: '.$environment,
            '',
        ];

        $groups = [];
        foreach ($results as $row) {
            $groups[$row['role']][] = $row;
        }

        foreach ($groups as $role => $rows) {
            $lines[] = $role.':';
            foreach ($rows as $row) {
                $lines[] = $row['status'].' — '.$row['workflow'].($row['step'] !== '' ? ' / '.$row['step'] : '');
                if ($row['status'] === 'FAIL') {
                    $lines[] = '  Role: '.$row['role'];
                    $lines[] = '  Workflow: '.$row['workflow'];
                    $lines[] = '  Step: '.$row['step'];
                    $lines[] = '  Error: '.($row['error'] ?? 'unknown');
                    $lines[] = '  Evidence: '.($row['evidence'] ?? 'n/a');
                }
            }
            $lines[] = '';
        }

        $lines[] = 'TOTAL:';
        $lines[] = $passed.' passed';
        $lines[] = $failed.' failed';
        $lines[] = $skipped.' skipped';
        $lines[] = '';

        $text = implode(PHP_EOL, $lines);
        $dir = storage_path('app/qa');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/AULASYNC-QA-REPORT.txt', $text);

        $evidenceDir = base_path('tests/evidence');
        File::ensureDirectoryExists($evidenceDir);
        File::put($evidenceDir.'/AULASYNC-QA-REPORT.txt', $text);

        return $text;
    }
}
