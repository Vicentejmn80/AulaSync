$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=== Nova Academy - Dev Environment ===" -ForegroundColor Cyan

$jobs = @()

# Start PHP server
$jobs += Start-Job -Name "server" -ScriptBlock {
    param($root)
    Set-Location $root
    php -S 127.0.0.1:8080 -t "$root\public" "$root\public\index.php"
} -ArgumentList $root

# Start queue
$jobs += Start-Job -Name "queue" -ScriptBlock {
    param($root)
    Set-Location $root
    php artisan queue:listen --tries=1 --timeout=0
} -ArgumentList $root

# Start Vite
$jobs += Start-Job -Name "vite" -ScriptBlock {
    param($root)
    Set-Location $root
    npm run dev
} -ArgumentList $root

Write-Host ""
Write-Host "  Server: http://localhost:8080" -ForegroundColor Green
Write-Host "  Vite:   http://localhost:5173" -ForegroundColor Green
Write-Host "  Queue:  listening..." -ForegroundColor Green
Write-Host ""
Write-Host "Press Ctrl+C to stop all processes" -ForegroundColor Yellow
Write-Host ""

try {
    while ($true) {
        $running = $jobs | Where-Object { $_.State -eq 'Running' }
        $failed = $jobs | Where-Object { $_.State -eq 'Failed' }
        
        if ($failed.Count -gt 0) {
            foreach ($j in $failed) {
                Write-Host "[FAILED] $($j.Name)" -ForegroundColor Red
                $j | Receive-Job
            }
            break
        }
        
        if ($running.Count -eq 0) {
            Write-Host "All processes stopped." -ForegroundColor Yellow
            break
        }
        
        Start-Sleep -Seconds 2
    }
}
finally {
    Write-Host "Stopping all processes..." -ForegroundColor Yellow
    $jobs | Stop-Job -PassThru | Remove-Job
}
