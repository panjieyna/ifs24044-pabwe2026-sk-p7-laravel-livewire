$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

if (-not (Test-Path -LiteralPath '.env')) {
    throw 'Salin .env.example ke .env dan jalankan setup pada README terlebih dahulu.'
}

# Use the isolated practice database prepared on this computer when configured.
$localConfig = Join-Path $PSScriptRoot '.qa/mysql/my.ini'
$localEnvironment = Get-Content -LiteralPath '.env'
if (($localEnvironment -contains 'DB_PORT=3307') -and (Test-Path -LiteralPath $localConfig)) {
    $connection = New-Object System.Net.Sockets.TcpClient
    try { $connection.Connect('127.0.0.1', 3307) } catch { } finally { $databaseRunning = $connection.Connected; $connection.Dispose() }
    if (-not $databaseRunning) {
        $mariaDirectory = Get-ChildItem -LiteralPath $env:ProgramFiles -Directory -Filter 'MariaDB*' |
            Sort-Object Name -Descending | Select-Object -First 1
        if (-not $mariaDirectory) { throw 'MariaDB lokal tidak ditemukan. Atur koneksi MySQL pada .env.' }
        $server = Join-Path $mariaDirectory.FullName 'bin/mariadbd.exe'
        $serverArguments = @(('--defaults-file="' + $localConfig + '"'), '--bind-address=127.0.0.1', '--port=3307')
        Start-Process -FilePath $server -ArgumentList $serverArguments -WindowStyle Hidden | Out-Null
        Start-Sleep -Seconds 2
    }
}

$env:XDEBUG_MODE = 'off'
php artisan serve --host=127.0.0.1 --port=8000 --no-interaction
exit $LASTEXITCODE
