$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

function Test-LocalPort {
    param([int]$Port)

    $connection = New-Object System.Net.Sockets.TcpClient
    try {
        $pendingConnection = $connection.ConnectAsync('127.0.0.1', $Port)
        return $pendingConnection.Wait(500) -and $connection.Connected
    } catch {
        return $false
    } finally {
        $connection.Dispose()
    }
}

if (-not (Test-Path -LiteralPath '.env')) {
    throw 'Salin .env.example ke .env dan jalankan setup pada README terlebih dahulu.'
}

# Use the isolated practice database prepared on this computer when configured.
$localConfig = Join-Path $PSScriptRoot '.qa/mysql/my.ini'
$localEnvironment = Get-Content -LiteralPath '.env'
if (($localEnvironment -contains 'DB_PORT=3307') -and (Test-Path -LiteralPath $localConfig)) {
    if (-not (Test-LocalPort -Port 3307)) {
        $mariaDirectory = Get-ChildItem -LiteralPath $env:ProgramFiles -Directory -Filter 'MariaDB*' |
            Sort-Object Name -Descending | Select-Object -First 1
        if (-not $mariaDirectory) { throw 'MariaDB lokal tidak ditemukan. Atur koneksi MySQL pada .env.' }
        $server = Join-Path $mariaDirectory.FullName 'bin/mariadbd.exe'
        $serverArguments = @(('--defaults-file="' + $localConfig + '"'), '--bind-address=127.0.0.1', '--port=3307')
        $databaseProcess = Start-Process -FilePath $server -ArgumentList $serverArguments -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput (Join-Path $PSScriptRoot '.qa/mysql-stdout.log') `
            -RedirectStandardError (Join-Path $PSScriptRoot '.qa/mysql-stderr.log')
        $startupTimer = [System.Diagnostics.Stopwatch]::StartNew()
        while (-not (Test-LocalPort -Port 3307)) {
            if ($databaseProcess.HasExited) {
                throw 'MariaDB gagal dimulai. Periksa .qa/mysql-stderr.log.'
            }
            if ($startupTimer.Elapsed.TotalSeconds -ge 20) {
                throw 'MariaDB belum siap setelah 20 detik. Periksa .qa/mysql-stderr.log.'
            }
            Start-Sleep -Milliseconds 200
        }
    }
    Write-Host 'Database lokal siap di 127.0.0.1:3307.'
}

if (Test-LocalPort -Port 8000) {
    Write-Host 'Web server sudah berjalan. Buka http://127.0.0.1:8000 atau refresh halaman browser.'
    exit 0
}

$env:XDEBUG_MODE = 'off'
php artisan serve --host=127.0.0.1 --port=8000 --no-interaction
exit $LASTEXITCODE
