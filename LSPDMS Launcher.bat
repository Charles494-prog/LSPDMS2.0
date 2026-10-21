@echo off
set "LSPDMS_MODE=%~1"
set "LSPDMS_LAUNCHER=%~f0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$f='%~f0'; $s=[IO.File]::ReadAllText($f); $i=$s.IndexOf('#'+'!PS!#'); if($i -lt 0){throw 'Launcher script section was not found.'}; Invoke-Expression $s.Substring($i)"
exit /b %errorlevel%
#!PS!#
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$RepoUrl = 'https://github.com/Charles494-prog/LSPDMS2.0'
$RepoBranch = 'master'
$AppDirectory = 'LSPDMS2.0'
$AppPort = 8000
$ReverbPort = 8080
$DatabaseName = 'lspdms'
$LogRoot = Join-Path $env:LOCALAPPDATA 'LSPDMS2.0\logs'
$RuntimeRoot = Join-Path $env:LOCALAPPDATA 'LSPDMS2.0\runtime'
$FirewallWebRule = 'LSPDMS Web - Private LAN'
$FirewallReverbRule = 'LSPDMS Reverb - Private LAN'

New-Item -ItemType Directory -Path $LogRoot, $RuntimeRoot -Force | Out-Null

function Write-Step([string]$Message) {
    Write-Host "`n== $Message ==" -ForegroundColor Cyan
}

function Sync-Path {
    $machinePath = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = "$machinePath;$userPath;$env:Path"
}

function Find-LaragonRoot {
    foreach ($root in @('C:\laragon', 'D:\laragon', 'E:\laragon', "$env:SystemDrive\laragon")) {
        if ((Test-Path (Join-Path $root 'www')) -and (Test-Path (Join-Path $root 'bin'))) {
            return $root
        }
    }

    return $null
}

function Add-LaragonTools([string]$LaragonRoot) {
    $paths = @()
    $phpRoot = Join-Path $LaragonRoot 'bin\php'
    $phpFolder = Get-ChildItem $phpRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } |
        Sort-Object Name -Descending |
        Select-Object -First 1
    if ($phpFolder) { $paths += $phpFolder.FullName }

    $nodeRoot = Join-Path $LaragonRoot 'bin\nodejs'
    $nodeFolder = Get-ChildItem $nodeRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { Test-Path (Join-Path $_.FullName 'npm.cmd') } |
        Sort-Object Name -Descending |
        Select-Object -First 1
    if ($nodeFolder) { $paths += $nodeFolder.FullName }

    $composerRoot = Join-Path $LaragonRoot 'bin\composer'
    $composerPhar = Get-ChildItem $composerRoot -Filter 'composer.phar' -File -Recurse -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($composerPhar -and $phpFolder) {
        $composerWrapper = Join-Path $composerRoot 'composer.bat'
        if (-not (Test-Path $composerWrapper)) {
            Set-Content -Path $composerWrapper -Encoding ASCII -Value "@echo off`r`n`"$($phpFolder.FullName)\php.exe`" `"$($composerPhar.FullName)`" %*"
        }
        $paths += $composerRoot
    }

    foreach ($path in $paths) {
        if (-not $env:Path.Split(';').Contains($path)) {
            $env:Path = "$path;$env:Path"
        }
    }
}

function Install-WingetPackage([string]$PackageId, [string]$DisplayName) {
    if (-not (Get-Command winget.exe -ErrorAction SilentlyContinue)) {
        throw "Windows Package Manager is unavailable. Install $DisplayName, then run this launcher again."
    }

    Write-Host "Installing $DisplayName..."
    & winget.exe install --id $PackageId --exact --silent --accept-package-agreements --accept-source-agreements
    Sync-Path
}

function Test-TcpPort([string]$HostName, [int]$Port) {
    $client = [Net.Sockets.TcpClient]::new()
    try {
        $result = $client.BeginConnect($HostName, $Port, $null, $null)
        return $result.AsyncWaitHandle.WaitOne(350) -and $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Find-LanAddress {
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue |
        Sort-Object RouteMetric |
        Select-Object -First 1
    if (-not $route) { return $null }

    $address = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $route.InterfaceIndex -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and -not $_.SkipAsSource } |
        Select-Object -First 1

    return $address.IPAddress
}

function Set-FirstInstallEnvironment([string]$ProjectRoot) {
    $envFile = Join-Path $ProjectRoot '.env'
    if (Test-Path $envFile) { return }
    $example = Join-Path $ProjectRoot '.env.example'
    if (-not (Test-Path $example)) { throw '.env.example is missing from the downloaded source.' }

    Copy-Item $example $envFile
    $contents = [IO.File]::ReadAllText($envFile)
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $keyBytes = New-Object byte[] 32
        $secretBytes = New-Object byte[] 32
        $random.GetBytes($keyBytes)
        $random.GetBytes($secretBytes)
        $reverbKey = -join ($keyBytes | ForEach-Object { $_.ToString('x2') })
        $reverbSecret = -join ($secretBytes | ForEach-Object { $_.ToString('x2') })
    } finally {
        $random.Dispose()
    }

    $contents = [regex]::Replace($contents, '(?m)^REVERB_APP_KEY=.*$', "REVERB_APP_KEY=$reverbKey")
    $contents = [regex]::Replace($contents, '(?m)^REVERB_APP_SECRET=.*$', "REVERB_APP_SECRET=$reverbSecret")
    [IO.File]::WriteAllText($envFile, $contents, [Text.UTF8Encoding]::new($false))
    Write-Host 'Created a fresh local .env from .env.example; no existing .env was changed.' -ForegroundColor Green
}

function Get-LaravelDatabaseConfig([string]$ProjectRoot, [string]$PhpPath) {
    $configScript = Join-Path $env:TEMP ("lspdms-db-config-" + [guid]::NewGuid().ToString('N') + '.php')
    $php = @'
<?php
$root = getenv('LSPDMS_PROJECT');
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$driver = config('database.default');
$settings = config('database.connections.' . $driver, []);
echo json_encode([
    'driver' => $driver,
    'host' => $settings['host'] ?? '',
    'port' => $settings['port'] ?? '',
    'database' => $settings['database'] ?? '',
]);
'@
    try {
        [IO.File]::WriteAllText($configScript, $php, [Text.UTF8Encoding]::new($false))
        $env:LSPDMS_PROJECT = $ProjectRoot
        $result = & $PhpPath $configScript 2>$null
        if ($LASTEXITCODE -ne 0) { throw 'Laravel could not read its database configuration.' }
        return ($result -join "`n") | ConvertFrom-Json
    } finally {
        Remove-Item Env:\LSPDMS_PROJECT -ErrorAction SilentlyContinue
        Remove-Item $configScript -Force -ErrorAction SilentlyContinue
    }
}

function Start-LocalMySql([string]$LaragonRoot, [int]$Port) {
    if (Test-TcpPort '127.0.0.1' $Port) {
        Write-Host "A database server is already listening on port $Port; leaving it untouched." -ForegroundColor Green
        return
    }

    $mysqlVersions = Join-Path $LaragonRoot 'bin\mysql'
    $mysql = Get-ChildItem $mysqlVersions -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending |
        ForEach-Object {
            $executable = Join-Path $_.FullName 'bin\mysqld.exe'
            $configuration = Join-Path $_.FullName 'my.ini'
            if ((Test-Path $executable) -and (Test-Path $configuration)) {
                [pscustomobject]@{ Exe = $executable; Config = $configuration; Bin = (Split-Path $executable) }
            }
        } |
        Select-Object -First 1

    if (-not $mysql) {
        $laragonExe = Join-Path $LaragonRoot 'laragon.exe'
        if (Test-Path $laragonExe) { Start-Process $laragonExe }
        throw "Laragon MySQL was not found. Start the MySQL service in Laragon, then run this launcher again."
    }

    Write-Host "Starting Laragon MySQL on port $Port..."
    $arguments = '--defaults-file="' + $mysql.Config + '" --console'
    $process = Start-Process -FilePath $mysql.Exe -ArgumentList $arguments -WorkingDirectory $mysql.Bin `
        -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $LogRoot 'mysql-startup.log') `
        -RedirectStandardError (Join-Path $LogRoot 'mysql-startup-error.log')
    Set-Content -Path (Join-Path $RuntimeRoot 'mysql.pid') -Value $process.Id -Encoding ASCII

    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        if (Test-TcpPort '127.0.0.1' $Port) {
            Write-Host 'MySQL is ready.' -ForegroundColor Green
            return
        }
        if ($process.HasExited) { break }
        Start-Sleep -Milliseconds 750
    }

    throw "MySQL did not start. Review Laragon's MySQL configuration and $LogRoot."
}

function Ensure-MySqlDatabase([string]$ProjectRoot, [string]$PhpPath) {
    $scriptPath = Join-Path $env:TEMP ("lspdms-create-database-" + [guid]::NewGuid().ToString('N') + '.php')
    $php = @'
<?php
$root = getenv('LSPDMS_PROJECT');
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$driver = config('database.default');
if (!in_array($driver, ['mysql', 'mariadb'], true)) { echo 'SKIP'; exit(0); }
$c = config('database.connections.' . $driver, []);
$name = (string)($c['database'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) { fwrite(STDERR, 'Invalid database name.'); exit(2); }
try {
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';charset=' . ($c['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $c['username'] ?? '', $c['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
    $quotedName = '`' . $name . '`';
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quotedName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo 'READY';
} catch (Throwable $error) {
    fwrite(STDERR, 'Could not prepare the configured database. Check local MySQL access and .env.');
    exit(1);
}
'@
    try {
        [IO.File]::WriteAllText($scriptPath, $php, [Text.UTF8Encoding]::new($false))
        $env:LSPDMS_PROJECT = $ProjectRoot
        $result = & $PhpPath $scriptPath 2>$null
        if ($LASTEXITCODE -ne 0 -or (($result -join '').Trim() -notin @('READY', 'SKIP'))) {
            throw 'The configured database could not be prepared. Check MySQL and .env database settings.'
        }
        if ((($result -join '').Trim()) -eq 'READY') {
            Write-Host 'Configured database is ready. Database creation does not create or alter tables; Laravel migrations do that.' -ForegroundColor Green
        }
    } finally {
        Remove-Item Env:\LSPDMS_PROJECT -ErrorAction SilentlyContinue
        Remove-Item $scriptPath -Force -ErrorAction SilentlyContinue
    }
}

function Ensure-InitialHeadSecretary([string]$ProjectRoot, [string]$PhpPath) {
    $checkScript = Join-Path $env:TEMP ("lspdms-check-head-" + [guid]::NewGuid().ToString('N') + '.php')
    $checkPhp = @'
<?php
$root = getenv('LSPDMS_PROJECT');
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$exists = App\Models\User::query()->whereRaw("LOWER(TRIM(tungkulin)) IN (?, ?)", ['head secretary', 'secretary head'])->exists();
echo $exists ? 'EXISTS' : 'MISSING';
'@
    try {
        [IO.File]::WriteAllText($checkScript, $checkPhp, [Text.UTF8Encoding]::new($false))
        $env:LSPDMS_PROJECT = $ProjectRoot
        $result = & $PhpPath $checkScript 2>$null
        if ($LASTEXITCODE -ne 0) { throw 'Could not check the Head Secretary account.' }
        if ((($result -join '').Trim()) -eq 'EXISTS') {
            Write-Host 'An existing Head Secretary was found; no account was changed.' -ForegroundColor Green
            return
        }
    } finally {
        Remove-Item Env:\LSPDMS_PROJECT -ErrorAction SilentlyContinue
        Remove-Item $checkScript -Force -ErrorAction SilentlyContinue
    }

    Write-Host "`nNo Head Secretary account exists in this database. Create the first account now." -ForegroundColor Yellow
    $email = (Read-Host 'Head Secretary email').Trim().ToLowerInvariant()
    $firstName = (Read-Host 'First name').Trim()
    $lastName = (Read-Host 'Last name').Trim()
    if ($email -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$' -or -not $firstName -or -not $lastName) {
        throw 'A valid email, first name, and last name are required for initial account setup.'
    }

    $password = Read-Host 'Password (minimum 8 characters)' -AsSecureString
    $confirmation = Read-Host 'Confirm password' -AsSecureString
    $passwordPointer = [IntPtr]::Zero
    $confirmationPointer = [IntPtr]::Zero
    try {
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($password)
        $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($confirmation)
        $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
        $plainConfirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)
        if ($plainPassword.Length -lt 8) { throw 'Password must contain at least 8 characters.' }
        if ($plainPassword -cne $plainConfirmation) { throw 'The passwords do not match.' }

        $payload = @{
            email = $email
            first_name = $firstName
            last_name = $lastName
            password = $plainPassword
        } | ConvertTo-Json -Compress
        $env:LSPDMS_BOOTSTRAP_DATA = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($payload))
    } finally {
        if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
        if ($confirmationPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer) }
        $plainPassword = $null
        $plainConfirmation = $null
        $password = $null
        $confirmation = $null
    }

    $createScript = Join-Path $env:TEMP ("lspdms-create-head-" + [guid]::NewGuid().ToString('N') + '.php')
    $createPhp = @'
<?php
$root = getenv('LSPDMS_PROJECT');
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $data = json_decode(base64_decode((string)getenv('LSPDMS_BOOTSTRAP_DATA')), true, 512, JSON_THROW_ON_ERROR);
    Illuminate\Support\Facades\DB::transaction(function () use ($data): void {
        if (App\Models\User::query()->whereRaw("LOWER(TRIM(tungkulin)) IN (?, ?)", ['head secretary', 'secretary head'])->lockForUpdate()->exists()) {
            throw new RuntimeException('A Head Secretary already exists.');
        }
        if (App\Models\User::query()->whereRaw('LOWER(TRIM(gmail)) = ?', [$data['email']])->exists()) {
            throw new RuntimeException('That email is already registered.');
        }
        App\Models\User::query()->create([
            'gmail' => $data['email'],
            'password' => $data['password'],
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'dashboard_allow' => true,
            'form_generator_allow' => true,
            'Delistment_allow' => true,
            'Inquire_Allow' => true,
            'tarheta_status_allow' => true,
            'filter_bnh_helper_allow' => true,
            'chatbot_records_allow' => true,
        ]);
    });
    echo 'CREATED';
} catch (Throwable $error) {
    fwrite(STDERR, 'Initial Head Secretary setup failed. Verify the email is unique and rerun the launcher.');
    exit(1);
} finally {
    putenv('LSPDMS_BOOTSTRAP_DATA');
}
'@
    try {
        [IO.File]::WriteAllText($createScript, $createPhp, [Text.UTF8Encoding]::new($false))
        $env:LSPDMS_PROJECT = $ProjectRoot
        $result = & $PhpPath $createScript 2>$null
        if ($LASTEXITCODE -ne 0 -or (($result -join '').Trim() -ne 'CREATED')) {
            throw 'Initial Head Secretary account creation failed.'
        }
        Write-Host 'Initial Head Secretary account created. The password was not written to a file or command line.' -ForegroundColor Green
    } finally {
        Remove-Item Env:\LSPDMS_PROJECT, Env:\LSPDMS_BOOTSTRAP_DATA -ErrorAction SilentlyContinue
        Remove-Item $createScript -Force -ErrorAction SilentlyContinue
        $payload = $null
        $plainPassword = $null
    }
}

function Add-PrivateFirewallRules {
    $webExists = @(Get-NetFirewallRule -DisplayName $FirewallWebRule -ErrorAction SilentlyContinue).Count -gt 0
    $reverbExists = @(Get-NetFirewallRule -DisplayName $FirewallReverbRule -ErrorAction SilentlyContinue).Count -gt 0
    if ($webExists -and $reverbExists) {
        Write-Host 'Private-network firewall rules already exist.' -ForegroundColor Green
        return
    }

    $answer = Read-Host 'Add Windows Firewall rules for Private LAN only (ports 8000 and 8080)? [Y/n]'
    if ($answer -and $answer -notmatch '^(y|yes)$') {
        Write-Host 'Skipped firewall setup. Windows may block other devices from connecting.'
        return
    }

    $firewallScript = @"
`$ErrorActionPreference = 'Stop'
Remove-NetFirewallRule -DisplayName '$FirewallWebRule' -ErrorAction SilentlyContinue
Remove-NetFirewallRule -DisplayName '$FirewallReverbRule' -ErrorAction SilentlyContinue
New-NetFirewallRule -DisplayName '$FirewallWebRule' -Direction Inbound -Action Allow -Protocol TCP -LocalPort $AppPort -Profile Private -RemoteAddress LocalSubnet | Out-Null
New-NetFirewallRule -DisplayName '$FirewallReverbRule' -Direction Inbound -Action Allow -Protocol TCP -LocalPort $ReverbPort -Profile Private -RemoteAddress LocalSubnet | Out-Null
"@
    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($firewallScript))
    try {
        $elevated = Start-Process -FilePath 'powershell.exe' -Verb RunAs -Wait -PassThru `
            -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded)
        if ($elevated.ExitCode -eq 0) {
            Write-Host 'Firewall rules allow only local-subnet traffic on Private networks.' -ForegroundColor Green
        } else {
            Write-Warning 'Firewall setup was not completed. Localhost still works; LAN access may be blocked.'
        }
    } catch {
        Write-Warning 'Firewall setup was cancelled or unavailable. Localhost still works; LAN access may be blocked.'
    }
}

function Stop-LspdmsProcesses {
    foreach ($name in @('web.pid', 'reverb.pid')) {
        $path = Join-Path $RuntimeRoot $name
        if (-not (Test-Path $path)) { continue }
        $pidValue = 0
        if (-not [int]::TryParse((Get-Content $path -ErrorAction SilentlyContinue | Select-Object -First 1), [ref]$pidValue)) {
            Remove-Item $path -Force -ErrorAction SilentlyContinue
            continue
        }

        $processInfo = Get-CimInstance Win32_Process -Filter "ProcessId = $pidValue" -ErrorAction SilentlyContinue
        if ($processInfo -and $processInfo.ExecutablePath -and
            $processInfo.ExecutablePath -like '*php.exe' -and
            $processInfo.CommandLine -like "*$ProjectRoot*" -and
            $processInfo.CommandLine -match 'artisan (serve|reverb:start)') {
            Stop-Process -Id $pidValue -ErrorAction SilentlyContinue
            Write-Host "Stopped LSPDMS process $pidValue."
        }
        Remove-Item $path -Force -ErrorAction SilentlyContinue
    }
}

function Start-LspdmsProcess([string]$PhpPath, [string]$ProjectRoot, [string[]]$Arguments, [string]$Name) {
    $stdout = Join-Path $LogRoot "$Name.log"
    $stderr = Join-Path $LogRoot "$Name-error.log"
    Remove-Item $stdout, $stderr -Force -ErrorAction SilentlyContinue
    $process = Start-Process -FilePath $PhpPath -ArgumentList $Arguments -WorkingDirectory $ProjectRoot `
        -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    Set-Content -Path (Join-Path $RuntimeRoot "$Name.pid") -Value $process.Id -Encoding ASCII
    return $process
}

try {
    if ($env:LSPDMS_MODE -eq 'stop') {
        $launcherDirectory = Split-Path -Parent $env:LSPDMS_LAUNCHER
        $ProjectRoot = if (Test-Path (Join-Path $launcherDirectory 'artisan')) {
            $launcherDirectory
        } else {
            $laragonRoot = Find-LaragonRoot
            if (-not $laragonRoot) { throw 'Laragon was not found; no launcher-managed project can be located.' }
            Join-Path (Join-Path $laragonRoot 'www') $AppDirectory
        }
        Stop-LspdmsProcesses
        Write-Host 'LSPDMS web and Reverb processes stopped. MySQL was left running to protect the database.'
        Read-Host 'Press Enter to close'
        exit 0
    }

    Write-Step 'Checking this PC'
    $laragonRoot = Find-LaragonRoot
    if (-not $laragonRoot) {
        Install-WingetPackage 'LeNgocKhoa.Laragon' 'Laragon (PHP and MySQL)'
        $laragonRoot = Find-LaragonRoot
    }
    if (-not $laragonRoot) { throw 'Laragon was not found after installation. Install it to C:\laragon, then rerun this launcher.' }
    Add-LaragonTools $laragonRoot

    if (-not (Get-Command php.exe -ErrorAction SilentlyContinue)) {
        throw 'Laragon PHP was not found. Add a PHP version using Laragon, then rerun this launcher.'
    }
    if (-not (Get-Command npm.cmd -ErrorAction SilentlyContinue)) {
        Install-WingetPackage 'OpenJS.NodeJS.LTS' 'Node.js LTS'
        Sync-Path
        Add-LaragonTools $laragonRoot
    }
    if (-not (Get-Command npm.cmd -ErrorAction SilentlyContinue)) { throw 'Node.js/npm installation did not complete.' }

    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        Install-WingetPackage 'Composer.Composer' 'Composer'
        Sync-Path
        Add-LaragonTools $laragonRoot
    }
    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $composer) { throw 'Composer was not found after installation.' }
    $php = (Get-Command php.exe).Source

    $modules = @(& $php -m 2>$null | ForEach-Object { $_.Trim().ToLowerInvariant() })
    $missingExtensions = @('openssl', 'mbstring', 'fileinfo', 'pdo_mysql', 'zip') | Where-Object { $modules -notcontains $_ }
    if ($missingExtensions) {
        throw "PHP is missing required extensions: $($missingExtensions -join ', '). Enable them in Laragon PHP and retry."
    }

    Write-Step 'Downloading LSPDMS source'
    $launcherDirectory = Split-Path -Parent $env:LSPDMS_LAUNCHER
    $projectRoot = if (Test-Path (Join-Path $launcherDirectory 'artisan')) {
        $launcherDirectory
    } else {
        Join-Path (Join-Path $laragonRoot 'www') $AppDirectory
    }
    if (Test-Path (Join-Path $projectRoot 'artisan')) {
        Write-Host "Using the existing project at $projectRoot. Existing source and data were not overwritten."
    } elseif (Test-Path $projectRoot) {
        throw "$projectRoot already exists but is not an LSPDMS project. Rename or back it up before retrying."
    } else {
        $zipPath = Join-Path $env:TEMP ("LSPDMS-" + [guid]::NewGuid().ToString('N') + '.zip')
        $extractPath = Join-Path $env:TEMP ("LSPDMS-source-" + [guid]::NewGuid().ToString('N'))
        try {
            $url = "$RepoUrl/archive/refs/heads/$RepoBranch.zip"
            Invoke-WebRequest -UseBasicParsing -Uri $url -OutFile $zipPath -TimeoutSec 180
            Expand-Archive -LiteralPath $zipPath -DestinationPath $extractPath -Force
            $source = Get-ChildItem $extractPath -Directory | Select-Object -First 1
            if (-not $source -or -not (Test-Path (Join-Path $source.FullName 'artisan'))) {
                throw 'The GitHub download did not contain a Laravel application.'
            }
            New-Item -ItemType Directory -Path $projectRoot -Force | Out-Null
            Copy-Item -Path (Join-Path $source.FullName '*') -Destination $projectRoot -Recurse -Force
            if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) { throw 'Could not install the downloaded source files.' }
            Write-Host "Downloaded $RepoBranch from $RepoUrl." -ForegroundColor Green
        } finally {
            Remove-Item $zipPath, $extractPath -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    Write-Step 'Preparing application dependencies and local settings'
    Set-FirstInstallEnvironment $projectRoot
    $env:APP_DEBUG = 'false'
    $env:COMPOSER_NO_INTERACTION = '1'
    $env:COMPOSER_PROCESS_TIMEOUT = '1200'

    if (-not (Test-Path (Join-Path $projectRoot 'vendor\autoload.php'))) {
        Write-Host 'Installing Laravel packages...'
        Push-Location $projectRoot
        try {
            & $composer.Source install --no-dev --prefer-dist --no-interaction --optimize-autoloader
            if ($LASTEXITCODE -ne 0) { throw 'Composer could not install the Laravel packages.' }
        } finally { Pop-Location }
    }

    if (-not (Test-Path (Join-Path $projectRoot 'node_modules\.bin\vite.cmd'))) {
        Write-Host 'Installing frontend packages...'
        Push-Location $projectRoot
        try {
            & npm.cmd ci --no-audit --no-fund
            if ($LASTEXITCODE -ne 0) { throw 'npm could not install the frontend packages.' }
        } finally { Pop-Location }
    }

    Write-Step 'Starting and preparing the database'
    $database = Get-LaravelDatabaseConfig $projectRoot $php
    if ($database.driver -in @('mysql', 'mariadb')) {
        Start-LocalMySql $laragonRoot ([int]$database.port)
        Ensure-MySqlDatabase $projectRoot $php
    } elseif ($database.driver -eq 'sqlite') {
        $sqlitePath = [string]$database.database
        if ($sqlitePath -and -not [IO.Path]::IsPathRooted($sqlitePath)) {
            $sqlitePath = Join-Path $projectRoot $sqlitePath
        }
        if ($sqlitePath -and -not (Test-Path $sqlitePath)) {
            New-Item -ItemType File -Path $sqlitePath -Force | Out-Null
        }
    } else {
        throw "Database driver '$($database.driver)' is not supported by this launcher. Configure MySQL or SQLite in .env."
    }

    Push-Location $projectRoot
    try {
        & $php artisan key:generate --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Laravel could not prepare APP_KEY.' }
        & $php artisan config:clear
        if ($LASTEXITCODE -ne 0) { throw 'Laravel configuration cache could not be cleared.' }
        & $php artisan route:clear
        if ($LASTEXITCODE -ne 0) { throw 'Laravel route cache could not be cleared.' }
        & $php artisan view:clear
        if ($LASTEXITCODE -ne 0) { throw 'Laravel view cache could not be cleared.' }

        Write-Host 'Applying Laravel migrations (no database reset or drop)...'
        & $php artisan migrate --force
        if ($LASTEXITCODE -ne 0) {
            throw 'Laravel migrations failed. Check the local database settings and see the error above.'
        }
        Ensure-InitialHeadSecretary $projectRoot $php
    } finally { Pop-Location }

    Write-Step 'Building assets for localhost and LAN'
    $lanAddress = Find-LanAddress
    $env:VITE_REVERB_HOST = if ($lanAddress) { $lanAddress } else { '127.0.0.1' }
    $env:VITE_REVERB_PORT = [string]$ReverbPort
    $env:VITE_REVERB_SCHEME = 'http'
    $hotMarker = Join-Path $projectRoot 'public\hot'
    if (Test-Path $hotMarker) { Remove-Item $hotMarker -Force }

    Push-Location $projectRoot
    try {
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Vite could not build the production assets.' }
    } finally { Pop-Location }

    if (Test-TcpPort '127.0.0.1' $AppPort) {
        throw "Port $AppPort is already in use. Close the other server or run 'LSPDMS Launcher.bat stop' for the launcher-managed instance."
    }
    if (-not (Test-TcpPort '127.0.0.1' $ReverbPort)) {
        Write-Host 'Starting Reverb for live messaging...'
        $reverb = Start-LspdmsProcess $php $projectRoot @('artisan', 'reverb:start', '--host=0.0.0.0', "--port=$ReverbPort") 'reverb'
        for ($attempt = 0; $attempt -lt 30 -and -not $reverb.HasExited; $attempt++) {
            if (Test-TcpPort '127.0.0.1' $ReverbPort) { break }
            Start-Sleep -Milliseconds 500
        }
        if (-not (Test-TcpPort '127.0.0.1' $ReverbPort)) {
            Write-Warning "Reverb did not start. Live messaging may be unavailable; see $LogRoot."
        }
    } else {
        Write-Host "Port $ReverbPort is already in use; leaving its existing process untouched."
    }

    Write-Host 'Starting the Laravel web server...'
    $web = Start-LspdmsProcess $php $projectRoot @('artisan', 'serve', '--host=0.0.0.0', "--port=$AppPort") 'web'
    $ready = $false
    for ($attempt = 0; $attempt -lt 40 -and -not $web.HasExited; $attempt++) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 "http://127.0.0.1:$AppPort/up"
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch {}
        Start-Sleep -Milliseconds 750
    }
    if (-not $ready) {
        throw "Laravel did not pass its health check. Review $LogRoot\web-error.log."
    }

    Write-Step 'Opening LAN access (optional)'
    Add-PrivateFirewallRules

    Write-Step 'LSPDMS is ready'
    Write-Host "Local: http://127.0.0.1:$AppPort" -ForegroundColor Green
    if ($lanAddress) {
        Write-Host "LAN:   http://$lanAddress`:$AppPort" -ForegroundColor Green
        Write-Host 'LAN access is limited to Private-network firewall rules when those rules are enabled.'
    } else {
        Write-Host 'No LAN address was detected; localhost access is available.'
    }
    Write-Host "`nLogs: $LogRoot"
    Write-Host "To stop only the launcher-managed web and Reverb processes, run: `"$env:LSPDMS_LAUNCHER`" stop"
    Start-Process "http://127.0.0.1:$AppPort"
    Read-Host 'Press Enter to close this launcher (servers will keep running)'
} catch {
    Write-Host "`nLSPDMS setup/startup failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Setup logs, when available: $LogRoot" -ForegroundColor Yellow
    Read-Host 'Press Enter to close'
    exit 1
}
