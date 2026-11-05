param(
    [switch]$Stop,
    [switch]$PrepareOnly
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$projectDirectory = [System.IO.Path]::GetFullPath($PSScriptRoot)
$httpsDirectory = Join-Path $projectDirectory '.qa/https'
$configurationPath = Join-Path $httpsDirectory 'httpd.conf'
$ownershipPath = Join-Path $httpsDirectory 'owner.json'
$pidPath = Join-Path $httpsDirectory 'httpd.pid'
$apacheDirectory = 'C:\xampp\apache'
$apacheExecutable = Join-Path $apacheDirectory 'bin/httpd.exe'
$hostname = 'ifs24044-pabwe2026-sk-p7-laravel-livewire'
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Test-LocalHttpsPort {
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

function Get-OwnedApacheProcess {
    if (-not (Test-Path -LiteralPath $ownershipPath)) {
        return $null
    }

    $owner = Get-Content -LiteralPath $ownershipPath -Raw | ConvertFrom-Json
    if ([System.IO.Path]::GetFullPath([string]$owner.configurationPath) -ne $configurationPath) {
        return $null
    }

    $apacheProcess = Get-Process -Id ([int]$owner.processId) -ErrorAction SilentlyContinue
    if (-not $apacheProcess) {
        return $null
    }

    if ($apacheProcess.ProcessName -ne 'httpd' -or $apacheProcess.Path -ne $apacheExecutable) {
        return $null
    }

    $recordedStartTime = [DateTime]::Parse([string]$owner.startTimeUtc).ToUniversalTime()
    if ([Math]::Abs(($apacheProcess.StartTime.ToUniversalTime() - $recordedStartTime).TotalSeconds) -gt 1) {
        return $null
    }

    if (-not (Test-Path -LiteralPath $pidPath) -or [int](Get-Content -LiteralPath $pidPath -Raw).Trim() -ne $apacheProcess.Id) {
        return $null
    }

    return $apacheProcess
}

function Start-LocalHttpsDatabase {
    $environmentPath = Join-Path $projectDirectory '.env'
    if (-not (Test-Path -LiteralPath $environmentPath)) {
        throw 'Salin .env.example ke .env dan ikuti setup proyek terlebih dahulu.'
    }

    $databaseConfiguration = Join-Path $projectDirectory '.qa/mysql/my.ini'
    $environmentLines = Get-Content -LiteralPath $environmentPath
    if (($environmentLines -contains 'DB_PORT=3307') -and (Test-Path -LiteralPath $databaseConfiguration)) {
        if (-not (Test-LocalHttpsPort -Port 3307)) {
            $mariaDirectory = Get-ChildItem -LiteralPath $env:ProgramFiles -Directory -Filter 'MariaDB*' |
                Sort-Object Name -Descending | Select-Object -First 1
            if (-not $mariaDirectory) {
                throw 'MariaDB lokal tidak ditemukan. Periksa koneksi database pada .env.'
            }

            $databaseExecutable = Join-Path $mariaDirectory.FullName 'bin/mariadbd.exe'
            $databaseArguments = @(('--defaults-file="' + $databaseConfiguration + '"'), '--bind-address=127.0.0.1', '--port=3307')
            $databaseProcess = Start-Process -FilePath $databaseExecutable -ArgumentList $databaseArguments -WindowStyle Hidden -PassThru `
                -RedirectStandardOutput (Join-Path $projectDirectory '.qa/mysql-stdout.log') `
                -RedirectStandardError (Join-Path $projectDirectory '.qa/mysql-stderr.log')
            $databaseTimer = [System.Diagnostics.Stopwatch]::StartNew()
            while (-not (Test-LocalHttpsPort -Port 3307)) {
                if ($databaseProcess.HasExited) {
                    throw 'MariaDB gagal dimulai. Periksa .qa/mysql-stderr.log.'
                }
                if ($databaseTimer.Elapsed.TotalSeconds -ge 20) {
                    throw 'MariaDB belum siap setelah 20 detik. Periksa .qa/mysql-stderr.log.'
                }
                Start-Sleep -Milliseconds 200
            }
        }
        Write-Host 'Database lokal siap di 127.0.0.1:3307.'
    }
}

$ownedApache = Get-OwnedApacheProcess
if ($Stop) {
    if ($ownedApache) {
        Stop-Process -Id $ownedApache.Id -ErrorAction Stop
        $ownedApache.WaitForExit()
        if ((Test-Path -LiteralPath $pidPath) -and [int](Get-Content -LiteralPath $pidPath -Raw).Trim() -eq $ownedApache.Id) {
            Remove-Item -LiteralPath $pidPath -ErrorAction Stop
        }
        Write-Host 'Server HTTPS milik proyek ini sudah dihentikan. Database tetap berjalan.'
    } else {
        Write-Host 'Tidak ada server HTTPS milik proyek ini yang sedang berjalan.'
    }
    exit 0
}

if ((Test-LocalHttpsPort -Port 443) -and -not $ownedApache) {
    throw 'Port 443 sedang digunakan layanan lain. Layanan tersebut tidak akan diubah atau dihentikan.'
}

if (-not (Test-Path -LiteralPath $apacheExecutable)) {
    throw 'Apache XAMPP tidak ditemukan di C:\xampp\apache\bin\httpd.exe.'
}

$phpCommand = Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1
$phpExecutable = $phpCommand.Source
$phpDirectory = Split-Path -Parent $phpExecutable
$phpModule = Join-Path $phpDirectory 'php8apache2_4.dll'
if (-not (Test-Path -LiteralPath $phpModule)) {
    throw 'PHP pada PATH harus menyediakan modul Thread Safe php8apache2_4.dll untuk Apache.'
}

$env:XDEBUG_MODE = 'off'
$env:PATH = $phpDirectory + [System.IO.Path]::PathSeparator + $env:PATH
$phpVersion = (& $phpExecutable -d xdebug.mode=off -r 'echo PHP_VERSION;').Trim()
if ($LASTEXITCODE -ne 0 -or [Version]$phpVersion -lt [Version]'8.4') {
    throw 'Gunakan PHP 8.4 atau lebih baru yang kompatibel dengan proyek.'
}

New-Item -ItemType Directory -Path $httpsDirectory -Force | Out-Null
$localPhpDirectory = Join-Path $httpsDirectory 'php'
New-Item -ItemType Directory -Path $localPhpDirectory -Force | Out-Null
$sourcePhpIni = (& $phpExecutable -d xdebug.mode=off -r 'echo php_ini_loaded_file();').Trim()
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $sourcePhpIni)) {
    throw 'Konfigurasi php.ini aktif tidak ditemukan.'
}

$phpIniContents = [System.IO.File]::ReadAllText($sourcePhpIni)
$phpExtensionDirectory = (Join-Path $phpDirectory 'ext').Replace('\', '/')
$extensionDirective = 'extension_dir = "' + $phpExtensionDirectory + '"'
if ($phpIniContents -match '(?m)^\s*extension_dir\s*=') {
    $phpIniContents = [Regex]::Replace($phpIniContents, '(?m)^\s*extension_dir\s*=.*$', $extensionDirective)
} else {
    $phpIniContents += [Environment]::NewLine + $extensionDirective
}
$phpIniContents = [Regex]::Replace($phpIniContents, '(?im)^\s*zend_extension\s*=.*xdebug.*$', '; Xdebug disabled for the local HTTPS server.')
$phpIniContents += [Environment]::NewLine + 'display_errors = Off' + [Environment]::NewLine + 'log_errors = On' + [Environment]::NewLine
[System.IO.File]::WriteAllText((Join-Path $localPhpDirectory 'php.ini'), $phpIniContents, $utf8)

$apacheRoot = $apacheDirectory.Replace('\', '/')
$publicRoot = (Join-Path $projectDirectory 'public').Replace('\', '/')
$httpsRoot = $httpsDirectory.Replace('\', '/')
$phpRoot = $phpDirectory.Replace('\', '/')
$localPhpRoot = $localPhpDirectory.Replace('\', '/')
$apacheSslDirectory = Join-Path $apacheDirectory 'bin'
$phpSslVersion = [Version](Get-Item -LiteralPath (Join-Path $phpDirectory 'libcrypto-3-x64.dll')).VersionInfo.FileVersion
$apacheSslVersion = [Version](Get-Item -LiteralPath (Join-Path $apacheSslDirectory 'libcrypto-3-x64.dll')).VersionInfo.FileVersion
$sslLibraryDirectory = $phpDirectory
if ($apacheSslVersion -gt $phpSslVersion) {
    $sslLibraryDirectory = $apacheSslDirectory
}
$sslLibraryRoot = $sslLibraryDirectory.Replace('\', '/')
$loadFiles = @()
foreach ($dependency in @('libcrypto-3-x64.dll', 'libssl-3-x64.dll')) {
    $loadFiles += ('LoadFile "' + $sslLibraryRoot + '/' + $dependency + '"')
}
foreach ($dependency in @('brotlicommon.dll', 'brotlidec.dll', 'nghttp2.dll', 'libssh2.dll', 'php8ts.dll', 'libsqlite3.dll', 'libpq.dll')) {
    if (Test-Path -LiteralPath (Join-Path $phpDirectory $dependency)) {
        $loadFiles += ('LoadFile "' + $phpRoot + '/' + $dependency + '"')
    }
}
$loadFileDirectives = $loadFiles -join [Environment]::NewLine
$apacheConfiguration = @"
ServerRoot "$apacheRoot"
ServerName localhost:80
Listen 127.0.0.1:443 https
PidFile "$httpsRoot/httpd.pid"
ErrorLog "$httpsRoot/error.log"
LogLevel warn
ServerTokens Prod
ServerSignature Off

# XAMPP has mpm_winnt compiled in. This isolated process loads no global config.
$loadFileDirectives
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule mime_module modules/mod_mime.so
LoadModule dir_module modules/mod_dir.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule php_module "$phpRoot/php8apache2_4.dll"
PHPIniDir "$localPhpRoot"

TypesConfig "$apacheRoot/conf/mime.types"
DirectoryIndex index.php
SSLSessionCache "shmcb:$httpsRoot/ssl-cache(512000)"
SSLSessionCacheTimeout 300

<Directory />
    AllowOverride None
    Require all denied
</Directory>

<VirtualHost 127.0.0.1:443>
    ServerName $hostname
    DocumentRoot "$publicRoot"
    SSLEngine on
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCertificateFile "$httpsRoot/server-cert.pem"
    SSLCertificateKeyFile "$httpsRoot/server-key.pem"
    CustomLog "$httpsRoot/access.log" common
    <Directory "$publicRoot">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php
    </FilesMatch>
    php_admin_flag display_errors Off
    php_admin_flag log_errors On
    php_admin_value error_log "$httpsRoot/php-error.log"
</VirtualHost>
"@
[System.IO.File]::WriteAllText($configurationPath, $apacheConfiguration, $utf8)

foreach ($certificateFile in @('server-cert.pem', 'server-key.pem')) {
    if (-not (Test-Path -LiteralPath (Join-Path $httpsDirectory $certificateFile))) {
        throw ('Sertifikat HTTPS belum disiapkan: .qa/https/' + $certificateFile)
    }
}

& $apacheExecutable -t -f $configurationPath
if ($LASTEXITCODE -ne 0) {
    throw 'Konfigurasi Apache HTTPS tidak valid. Periksa pesan di atas dan .qa/https/error.log.'
}

if ($PrepareOnly) {
    Write-Host 'Konfigurasi HTTPS siap dan valid. Tidak ada server yang dinyalakan.'
    exit 0
}

Start-LocalHttpsDatabase
if ($ownedApache -and (Test-LocalHttpsPort -Port 443)) {
    Write-Host ('Server HTTPS proyek sudah berjalan: https://' + $hostname)
    exit 0
}

# -X keeps this local development server in one owned process for safe shutdown.
$apacheArguments = @('-X', '-f', ('"' + $configurationPath + '"'))
$serverProcess = Start-Process -FilePath $apacheExecutable -ArgumentList $apacheArguments -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $httpsDirectory 'stdout.log') `
    -RedirectStandardError (Join-Path $httpsDirectory 'stderr.log')
$ownerRecord = @{
    processId = $serverProcess.Id
    startTimeUtc = $serverProcess.StartTime.ToUniversalTime().ToString('o')
    configurationPath = $configurationPath
}
[System.IO.File]::WriteAllText($ownershipPath, ($ownerRecord | ConvertTo-Json), $utf8)

$startupTimer = [System.Diagnostics.Stopwatch]::StartNew()
while (-not (Test-LocalHttpsPort -Port 443)) {
    if ($serverProcess.HasExited) {
        throw 'Apache HTTPS gagal dimulai. Periksa .qa/https/stderr.log dan error.log.'
    }
    if ($startupTimer.Elapsed.TotalSeconds -ge 20) {
        $serverProcess.Kill()
        throw 'Apache HTTPS belum siap setelah 20 detik. Periksa .qa/https/error.log.'
    }
    Start-Sleep -Milliseconds 200
}

[System.IO.File]::WriteAllText($pidPath, [string]$serverProcess.Id, $utf8)
Write-Host ('HTTPS lokal siap: https://' + $hostname)
Write-Host 'Untuk menghentikan hanya server HTTPS proyek: .\start-https.bat -Stop'
