param(
    [switch]$PrepareOnly,
    [switch]$SystemSetup,
    [string]$CertificateOwnerSid
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$localHostname = 'ifs24044-pabwe2026-sk-p7-laravel-livewire'
$localUrl = 'https://' + $localHostname
$tlsDirectory = Join-Path $PSScriptRoot '.qa/https'
$hostsPath = Join-Path $env:SystemRoot 'System32/drivers/etc/hosts'

function Add-ProjectHostsEntry {
    $existingLines = @(Get-Content -LiteralPath $hostsPath)
    $matches = @($existingLines | Where-Object {
        $parts = (($_ -split '#', 2)[0].Trim() -split '\s+')
        $parts.Count -ge 2 -and $parts[1..($parts.Count - 1)] -contains $localHostname
    })
    if ($matches.Count -gt 0) {
        foreach ($entry in $matches) {
            if (($entry.Trim() -split '\s+')[0] -ne '127.0.0.1') {
                throw 'Hostname sudah dipetakan ke alamat lain di hosts. Tidak ada entri yang diubah.'
            }
        }
        return
    }
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw (New-Object System.UnauthorizedAccessException('Penambahan hostname pada hosts memerlukan izin Administrator Windows.'))
    }
    Add-Content -LiteralPath $hostsPath -Encoding ASCII -Value ("`r`n127.0.0.1 " + $localHostname + ' # Ruang Stok local HTTPS')
}

function Protect-LocalPrivateKeys {
    param([string]$OwnerSid)

    $ownerIdentity = New-Object System.Security.Principal.SecurityIdentifier($OwnerSid)
    foreach ($filename in @('root-key.pem', 'server-key.pem')) {
        $keyPath = Join-Path $tlsDirectory $filename
        $previousOwner = (Get-Acl -LiteralPath $keyPath).Owner
        $keySecurity = New-Object System.Security.AccessControl.FileSecurity
        $keySecurity.SetAccessRuleProtection($true, $false)
        $keySecurity.SetOwner($ownerIdentity)
        foreach ($allowedSid in @($OwnerSid, 'S-1-5-18', 'S-1-5-32-544')) {
            $identity = New-Object System.Security.Principal.SecurityIdentifier($allowedSid)
            $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'Allow')
            $keySecurity.AddAccessRule($rule)
        }
        # The existing local Apache may run as the Codex sandbox account.
        if ($filename -eq 'server-key.pem' -and $previousOwner -match '\\CodexSandbox') {
            $runtimeIdentity = New-Object System.Security.Principal.NTAccount($previousOwner)
            $runtimeSid = $runtimeIdentity.Translate([System.Security.Principal.SecurityIdentifier])
            $runtimeRule = New-Object System.Security.AccessControl.FileSystemAccessRule($runtimeSid, 'Read', 'Allow')
            $keySecurity.AddAccessRule($runtimeRule)
        }
        Set-Acl -LiteralPath $keyPath -AclObject $keySecurity
    }
}

if ($SystemSetup) {
    if ($CertificateOwnerSid -notmatch '^S-1-5-21-\d+-\d+-\d+-\d+$') {
        throw 'SID pemilik sertifikat Windows tidak valid.'
    }
    Add-ProjectHostsEntry
    Protect-LocalPrivateKeys -OwnerSid $CertificateOwnerSid
    exit 0
}

if (-not (Test-Path -LiteralPath '.env')) {
    throw 'Siapkan .env aplikasi terlebih dahulu sesuai README.'
}
New-Item -ItemType Directory -Force -Path $tlsDirectory | Out-Null
$opensslPath = 'C:/xampp/apache/bin/openssl.exe'
if (-not (Test-Path -LiteralPath $opensslPath)) {
    $opensslCommand = Get-Command openssl -ErrorAction SilentlyContinue
    if (-not $opensslCommand) { throw 'OpenSSL tidak ditemukan. Siapkan OpenSSL atau XAMPP untuk HTTPS lokal.' }
    $opensslPath = $opensslCommand.Source
}

function Invoke-LocalOpenSsl {
    param([string[]]$Arguments)
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $result = & $opensslPath @Arguments 2>&1
    $commandExitCode = $LASTEXITCODE
    $ErrorActionPreference = $previousPreference
    if ($commandExitCode -ne 0) { throw ('OpenSSL gagal: ' + ($result -join "`n")) }
}

$rootCert = Join-Path $tlsDirectory 'root-cert.pem'
$rootKey = Join-Path $tlsDirectory 'root-key.pem'
$rootDer = Join-Path $tlsDirectory 'root-cert.cer'
$serverCert = Join-Path $tlsDirectory 'server-cert.pem'
$serverKey = Join-Path $tlsDirectory 'server-key.pem'
$utf8 = New-Object System.Text.UTF8Encoding($false)

$caConfig = @'
[req]
prompt = no
distinguished_name = dn
x509_extensions = ca_extensions
[dn]
CN = Ruang Stok Local Development CA
[ca_extensions]
basicConstraints = critical,CA:TRUE,pathlen:0
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always
'@
$serverConfig = @"
[req]
prompt = no
distinguished_name = dn
[dn]
CN = $localHostname
"@
$serverExtensions = @"
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature,keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = DNS:$localHostname
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
"@
[System.IO.File]::WriteAllText((Join-Path $tlsDirectory 'ca.cnf'), $caConfig, $utf8)
[System.IO.File]::WriteAllText((Join-Path $tlsDirectory 'server.cnf'), $serverConfig, $utf8)
[System.IO.File]::WriteAllText((Join-Path $tlsDirectory 'server.ext'), $serverExtensions, $utf8)

if (-not (Test-Path -LiteralPath $rootCert)) {
    if (Test-Path -LiteralPath $rootKey) { throw 'Kunci CA sudah ada tanpa sertifikatnya. Periksa berkas lokal sebelum membuat ulang.' }
    Invoke-LocalOpenSsl -Arguments @('req', '-x509', '-newkey', 'rsa:3072', '-nodes', '-sha256', '-days', '1825', '-config', (Join-Path $tlsDirectory 'ca.cnf'), '-keyout', $rootKey, '-out', $rootCert)
}
if (-not (Test-Path -LiteralPath $serverCert)) {
    if (Test-Path -LiteralPath $serverKey) { throw 'Kunci server sudah ada tanpa sertifikatnya. Periksa berkas lokal sebelum membuat ulang.' }
    $csrPath = Join-Path $tlsDirectory 'server.csr'
    Invoke-LocalOpenSsl -Arguments @('req', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-config', (Join-Path $tlsDirectory 'server.cnf'), '-keyout', $serverKey, '-out', $csrPath)
    Invoke-LocalOpenSsl -Arguments @('x509', '-req', '-in', $csrPath, '-CA', $rootCert, '-CAkey', $rootKey, '-CAcreateserial', '-out', $serverCert, '-days', '365', '-sha256', '-extfile', (Join-Path $tlsDirectory 'server.ext'))
}
Invoke-LocalOpenSsl -Arguments @('verify', '-verify_hostname', $localHostname, '-CAfile', $rootCert, $serverCert)
Invoke-LocalOpenSsl -Arguments @('x509', '-in', $serverCert, '-checkend', '86400', '-noout')
Invoke-LocalOpenSsl -Arguments @('x509', '-in', $rootCert, '-outform', 'DER', '-out', $rootDer)
Write-Host ('Sertifikat lokal siap untuk ' + $localUrl)

if ($PrepareOnly) {
    Write-Host 'Belum ada perubahan hosts, trust store, atau .env.'
    exit 0
}

$windowsIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
if ($windowsIdentity.Name -match '\\CodexSandbox') {
    throw 'Aktivasi sertifikat harus dijalankan dari akun Windows Anda, bukan akun sandbox.'
}
$certificateUserSid = $windowsIdentity.User.Value
$rootCertificate = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($rootDer)

try {
    Add-ProjectHostsEntry
    Protect-LocalPrivateKeys -OwnerSid $certificateUserSid
} catch [System.UnauthorizedAccessException] {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw }
    Write-Host 'Windows akan meminta izin Administrator untuk hostname lokal dan pembatasan akses private key.'
    $adminArguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + $PSCommandPath + '"'), '-SystemSetup', '-CertificateOwnerSid', $certificateUserSid)
    $adminProcess = Start-Process -FilePath 'powershell.exe' -ArgumentList $adminArguments -Verb RunAs -WindowStyle Hidden -Wait -PassThru
    if ($adminProcess.ExitCode -ne 0) { throw 'Penambahan hostname belum berhasil. Jalankan setup lagi dan izinkan permintaan Administrator Windows.' }
}

$trustedPath = 'Cert:\CurrentUser\Root\' + $rootCertificate.Thumbprint
if (-not (Test-Path -LiteralPath $trustedPath)) {
    $certificateStore = New-Object System.Security.Cryptography.X509Certificates.X509Store('Root', 'CurrentUser')
    try {
        $certificateStore.Open([System.Security.Cryptography.X509Certificates.OpenFlags]::ReadWrite)
        $certificateStore.Add($rootCertificate)
    } finally {
        $certificateStore.Close()
    }
}

$environmentPath = Join-Path $PSScriptRoot '.env'
$environmentText = [System.IO.File]::ReadAllText($environmentPath)
if ($environmentText -match '(?m)^APP_URL=') {
    $environmentText = [regex]::Replace($environmentText, '(?m)^APP_URL=[^\r\n]*', ('APP_URL=' + $localUrl))
} else {
    $environmentText += "`r`nAPP_URL=$localUrl`r`n"
}
[System.IO.File]::WriteAllText($environmentPath, $environmentText, $utf8)
Write-Host ('HTTPS lokal terpasang: ' + $localUrl)
Write-Host 'Jalankan .\start-https.bat untuk menyalakan aplikasi HTTPS.'
