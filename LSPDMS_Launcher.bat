@echo off
setlocal DisableDelayedExpansion
title LSPDMS
rem Arguments (optional, any order): lan, local, stop, dev, build, ascii
rem The paths and arguments reach PowerShell through environment variables, never pasted into its code.
set "LSPDMS_SELF=%~f0"
set "LSPDMS_ARG1=%~1"
set "LSPDMS_ARG2=%~2"
fltmc >nul 2>&1 && goto :isadmin
powershell -NoProfile -ExecutionPolicy Bypass -Command "$a = @(); foreach ($x in @($env:LSPDMS_ARG1, $env:LSPDMS_ARG2)) { if ($x -match '^(lan|local|stop|dev|build|ascii)$') { $a += $x } }; try { if ($a.Count -gt 0) { Start-Process -FilePath $env:LSPDMS_SELF -ArgumentList $a -Verb RunAs -ErrorAction Stop } else { Start-Process -FilePath $env:LSPDMS_SELF -Verb RunAs -ErrorAction Stop }; exit 0 } catch { exit 1 }"
if not errorlevel 1 exit /b
:isadmin
rem Runs the PowerShell part below the marker line. Only this file is read, and the marker must appear exactly once.
powershell -NoProfile -ExecutionPolicy Bypass -Command "$f = $env:LSPDMS_SELF; $m = [string][char]10 + '#' + '!PS!#'; $s = [IO.File]::ReadAllText($f, [Text.Encoding]::UTF8); $i = $s.IndexOf($m); if ($i -lt 0 -or $s.IndexOf($m, $i + 1) -ge 0) { Write-Host 'LSPDMS launcher: this file is damaged (start marker missing or repeated).' -ForegroundColor Red; Read-Host 'Press Enter to close'; exit 2 }; . ([ScriptBlock]::Create($s.Substring($i + 1)))"
exit /b
#!PS!#
$ErrorActionPreference = 'SilentlyContinue'
try { [Console]::OutputEncoding = [Text.Encoding]::UTF8 } catch {}

# ====================== CONFIG (edit these) ======================
$Project      = 'C:\laragon\www\LSPDMSfinal'   # Laragon ONLY: always inside <laragon>\www (checked, downloaded and updated there; XAMPP/htdocs is never used)
$AppHost      = '127.0.0.1'
$AppPort      = 8000
$UseReverb    = $true
$ReverbPort   = 8080
$UseMySQL     = $true
$MySQLPort    = 3306
$MySQLRoot    = 'C:\laragon\bin\mysql'
$MySQLHome    = 'C:\xampp\mysql'  # the MySQL/MariaDB folder that holds the LIVE data (bin\mysqld.exe + bin\my.ini). Used before Laragon's MySQL when no database Windows service exists. '' = Laragon only
$OpenBrowser  = $true
$AskMode      = $true    # $true = ask at startup: team/network or this device only
$DefaultMode  = 'local'  # used when $AskMode = $false  ('local' or 'lan')
$StaticIp     = ''       # optional: force the network IP shown/used, e.g. '192.168.1.2'
$AutoFixNetwork = $false # $true = if there is no IP, also release/renew + reconnect the adapter (can briefly drop Wi-Fi). Default: only FIND the current IP
$AllowFixedFallback = $false  # $true = LAST RESORT only: temporary fixed IP if the router never answers (default: never use a fixed IP)
$FallbackIp   = '192.168.1.2'  # only used when $AllowFixedFallback = $true
$RequireInternet = $false  # $true = choosing Team/network also requires REAL internet (ping test). $false = only needs a network connection (works on a hotspot with no data)
$OpenFirewall = $true    # network mode: open ports for other devices (needs Administrator)
$RenewIp      = $false   # $true = release/renew IP on launch (NOT needed; causes popups on hotspot)
$AutoInstall  = $true    # $true = at startup, install / fix everything that is missing by itself (no button press): Composer, packages, .env, PHP extensions in php.ini
$CheckDeps    = $true    # check PHP / Composer / Node / MySQL / project packages at startup (offers to download what is missing)
$AutoMigrate  = $true    # run "php artisan migrate" on every launch (safe: only adds missing tables, never deletes data). Also press [M] on the online screen
$RequireMasterAccount = $true  # $true = on first launch (no Head Secretary in the database yet) ask for one and save it as the master account. Does nothing once one exists
$AutoFixDbEnv = $true   # $true = if the project's .env still uses SQLite (Laravel's default) switch it to MySQL automatically (needed because .env is never downloaded from GitHub). Only changes .env when DB_CONNECTION is not mysql/mariadb
$DbName       = 'lspdms'   # database name used when .env still has Laravel's default ('laravel') or nothing
$KeepAwake    = $true    # network mode: stop Windows sleeping while LSPDMS runs (laptops: keep it plugged in)
# winget package ids used by the "install missing" option (if winget fails, the official download page is opened instead)
$WingetIds    = @{ laragon = 'LeNgocKhoa.Laragon'; node = 'OpenJS.NodeJS.LTS'; composer = 'Composer.Composer' }
$DownloadUrls = @{ laragon = 'https://laragon.org/download/'; node = 'https://nodejs.org/en/download'; composer = 'https://getcomposer.org/download/' }
# ---- Composer auto-install: downloads composer.phar directly (no winget / installer needed; only PHP is required) ----
$ComposerPharUrl = 'https://getcomposer.org/download/latest-stable/composer.phar'
$ToolsDir        = Join-Path $env:ProgramData 'LSPDMS\tools'   # composer.phar is saved here
# ---- source code download from GitHub (used by Required Dependencies > Install missing) ----
$RepoUrl      = 'https://github.com/Charles494-prog/LSPDMS2.0'
$RepoBranch   = ''   # '' = the repository's default branch (main or master). Or set one, e.g. 'main'
$SourcePasswordHash = 'ae28da629fe0a2f92d48a2b3cd52fd86dcc6ee8c115df1a163775031f1b7f4bd'   # SHA-256 of the download password ('' = no password asked). The password itself is NOT stored here
$CheckDatabase = $false   # the dependency check also tests the MySQL login from the project's .env
# ---- version check: compare this PC with the latest push on GitHub ----
$CheckUpdates   = $true   # at startup, check whether GitHub has a newer version (needs internet; with no internet it is skipped in ~2 s and LSPDMS starts as usual)
$UpdateRequired = $false  # $false = a newer version is only offered ("Update now" or "Skip for now"; the old version keeps working). $true = must update (or quit). A first-time setup with no code on the PC always downloads it
$VersionFile    = '.lspdms-version'   # remembers which GitHub commit this PC has (saved inside the project folder, never overwritten by updates)
# ---- speed / services (added Oct 2026) ----
$FrontEnd     = 'auto'   # 'auto' = this-PC mode keeps a Vite dev server that is already running, otherwise compiled assets (rebuilt only when resources/ changed) | 'build' = always compiled assets | 'dev' = start the Vite dev server (live reload, this PC only). Also: run with the argument  dev  or  build
$VitePort     = 4000     # Vite dev server port (vite.config.js)
$UseOpcache   = $true    # start the web server with PHP OPcache on (php.ini is NOT changed). Edited PHP files are still picked up on the next request
$WarmCaches   = $true    # compiled-assets mode: pre-compile Blade views + the event list (php artisan view:cache / event:cache)
$BackupBeforeMigrate = $true   # mysqldump the database to storage\app\manual-backups before NEW migrations run (they are skipped if the backup fails)
$KeepBackups  = 10       # how many launcher backups (pre-migrate-*.sql) are kept; older ones are deleted
$SecureDatabase = $true  # at startup (when .env DB_HOST is this PC): MariaDB listens on 127.0.0.1 only (bind-address in its my.ini, restarted once), inbound firewall Allow rules for mysqld / port 3306 are switched off (not deleted), and LSPDMS logs in with its own account instead of root (password made once, saved in .env)
$Scheduler    = 'auto'   # 'auto' = use the Windows task below when it exists, else run "php artisan schedule:work" while LSPDMS runs | 'task' = create the Windows task when it is missing | 'work' = always schedule:work | 'off'
$SchedulerTask = 'LSPDMS Scheduler'   # Windows Task Scheduler task that runs scripts\windows\run-scheduler.vbs every minute (daily message cleanup, monthly DB backup)
$AsciiUi      = $false   # $true = plain ASCII screen for old consoles that show boxes as '?'. Also: run with the argument  ascii
# =================================================================

$Url  = "http://${AppHost}:$AppPort"
$LanMode = $false   # decided at startup (menu)
$UseReverbSetting = $UseReverb   # $UseReverb itself also follows BROADCAST_CONNECTION in .env (Update-PortsFromEnv)
$LaunchArgs = @(@($env:LSPDMS_ARG1, $env:LSPDMS_ARG2) | Where-Object { $_ } | ForEach-Object { ([string]$_).ToLower() })
if ($LaunchArgs -contains 'dev') { $FrontEnd = 'dev' } elseif ($LaunchArgs -contains 'build') { $FrontEnd = 'build' }
if ($LaunchArgs -contains 'ascii') { $AsciiUi = $true }

function Get-LanIp {
    if ($StaticIp) { return $StaticIp }
    $bad = 'WARP|VPN|vEthernet|Loopback|VirtualBox|VMware|Hyper-V|Bluetooth|Docker|WSL|TAP|Tailscale|ZeroTier|Host-Only|Local Area Connection\*'
    try {
        # only addresses in the Preferred state: a Duplicate/Tentative address is NOT reachable
        $addrs = @(Get-NetIPAddress -AddressFamily IPv4 -AddressState Preferred -ErrorAction Stop |
            Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixLength -lt 32 -and $_.InterfaceAlias -notmatch $bad })
        $up = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' } | ForEach-Object { $_.ifIndex })
        if ($up.Count -gt 0) { $addrs = @($addrs | Where-Object { $up -contains $_.InterfaceIndex }) }
        $gwIf = @(Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue | ForEach-Object { $_.InterfaceIndex })
        $pick = $addrs | Where-Object { $gwIf -contains $_.InterfaceIndex } | Select-Object -First 1
        if (-not $pick) { $pick = $addrs | Select-Object -First 1 }
        if ($pick) { $script:LanIf = [string]$pick.InterfaceAlias; return [string]$pick.IPAddress }
    } catch {}
    return $null
}
$LanIp    = $null
$BindHost = $AppHost
$LanUrl   = $null
$W    = 74
$SP   = @('|','/','-','\')
# logs + state live in the project (storage\logs\launcher, storage\app\launcher) once it exists - see Set-LauncherPaths.
# Until then (first install) they go to ProgramData. Every path here is absolute.
$Logs     = Join-Path $env:ProgramData 'LSPDMS\logs'
$StateDir = Join-Path $env:ProgramData 'LSPDMS\state'
$NetStateFile = Join-Path $env:ProgramData 'LSPDMS\static-if.txt'   # a temporary fixed IP to undo (machine-wide, not per project)
try { New-Item -ItemType Directory -Force $Logs, $StateDir | Out-Null } catch {}

# ---- screen characters: box drawing on UTF-8 consoles, plain ASCII otherwise ----
if (-not $AsciiUi) {
    try { if ([Console]::OutputEncoding.CodePage -ne 65001) { $AsciiUi = $true } } catch { $AsciiUi = $true }
}
if ($AsciiUi) {
    $G = @{ TL = '+'; TR = '+'; BL = '+'; BR = '+'; H = '='; V = '|'; ML = '+'; MR = '+'; Full = '#'; Empty = '.'; Pend = 'o'; Ok = '+'; Warn = '!'; Fail = 'x'; Dot = '*'; Arrow = '>'
            RTL = '+'; RTR = '+'; RBL = '+'; RBR = '+'; RH = '-'; RV = '|'; Ell = '~'; Left = '<'; Right = '>'; Up = '^'; Down = 'v'; LogBar = '|' }
} else {
    # thin rounded frame, slim progress track (filled and empty use the same glyph; color tells them apart)
    $G = @{ TL = '╭'; TR = '╮'; BL = '╰'; BR = '╯'; H = '─'; V = '│'; ML = '├'; MR = '┤'; Full = '━'; Empty = '━'; Pend = '○'; Ok = '✓'; Warn = '!'; Fail = '✕'; Dot = '●'; Arrow = '›'
            RTL = '╭'; RTR = '╮'; RBL = '╰'; RBR = '╯'; RH = '─'; RV = '│'; Ell = '…'; Left = '←'; Right = '→'; Up = '↑'; Down = '↓'; LogBar = '│' }
    $SP = @('⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏')
}

$script:Procs = @()
$script:StartedMySQL = $false
$script:Steps = @()
$script:pct = 0
$script:spin = 0
$script:Banner = $null
$script:Theme = 'Cyan'
$script:Badge = ''
$script:BC = 'Cyan'
$script:T0 = Get-Date
$script:FailStep = $null
$script:FailLog = $null

$Logo = @(
'██╗     ███████╗██████╗ ██████╗ ███╗   ███╗███████╗',
'██║     ██╔════╝██╔══██╗██╔══██╗████╗ ████║██╔════╝',
'██║     ███████╗██████╔╝██║  ██║██╔████╔██║███████╗',
'██║     ╚════██║██╔═══╝ ██║  ██║██║╚██╔╝██║╚════██║',
'███████╗███████║██║     ██████╔╝██║ ╚═╝ ██║███████║',
'╚══════╝╚══════╝╚═╝     ╚═════╝ ╚═╝     ╚═╝╚══════╝'
)
if ($AsciiUi) {
    $Logo = @(
    ' _       ____   ____   ____   __  __  ____ ',
    '| |     / ___| |  _ \ |  _ \ |  \/  |/ ___|',
    '| |     \___ \ | |_) || | | || |\/| |\___ \',
    '| |___   ___) ||  __/ | |_| || |  | | ___) |',
    '|_____| |____/ |_|    |____/ |_|  |_||____/',
    ''
    )
}

# ---------------------------- drawing helpers ----------------------------
# Colors are names. On consoles with 24-bit color (VT) they map to a monochrome palette with color kept for
# status (Vercel-like); otherwise to the nearest classic console color. 'Soft*' = tinted backgrounds for pills.
$script:Vt = $false
$Esc = [char]27
$Pal = @{
    Black = '0;0;0'; White = '237;237;237'; Ink = '255;255;255'; Gray = '161;161;161'; DarkGray = '110;110;110'; Faint = '72;72;72'; Line = '46;46;46'
    Green = '98;192;115'; DarkGreen = '41;124;59'; Yellow = '245;165;36'; DarkYellow = '171;100;0'; Red = '255;97;102'; DarkRed = '229;72;77'
    Cyan = '82;168;255'; DarkCyan = '50;145;255'; Blue = '82;168;255'; DarkBlue = '0;112;243'; Magenta = '196;132;255'; DarkMagenta = '142;78;198'
    SoftGreen = '15;36;22'; SoftYellow = '42;32;16'; SoftRed = '42;19;20'; SoftCyan = '10;28;48'; SoftGray = '28;28;28'
}
$Classic = @{ Ink = 'White'; Faint = 'DarkGray'; Line = 'DarkGray'; SoftGreen = 'DarkGreen'; SoftYellow = 'DarkYellow'; SoftRed = 'DarkRed'; SoftCyan = 'DarkCyan'; SoftGray = 'DarkGray' }
function Rgb($name) { if ($name -like 'rgb:*') { $name.Substring(4) } else { $Pal[[string]$name] } }
function Seg($t, $f = 'Gray', $b = $null) {
    if ($script:Vt) {
        $s = "$Esc[38;2;$(Rgb $f)m"
        if ($b) { $s += "$Esc[48;2;$(Rgb $b)m" }
        [Console]::Write($s + $t + "$Esc[0m")
        return
    }
    if ($f -like 'rgb:*') { $f = 'Gray' }
    if ($Classic.ContainsKey([string]$f)) { $f = $Classic[[string]$f] }
    if ($b -and $Classic.ContainsKey([string]$b)) { $b = $Classic[[string]$b] }
    [Console]::ForegroundColor = [ConsoleColor]$f
    if ($b) { [Console]::BackgroundColor = [ConsoleColor]$b }
    [Console]::Write($t)
    if ($b) { [Console]::BackgroundColor = [ConsoleColor]'Black' }
}
function NL { [Console]::WriteLine() }
function Pt($t, $fg = 'Gray', $bg = $null) { [pscustomobject]@{ T = [string]$t; F = $fg; B = $bg } }
function Res($s, $d) { [pscustomobject]@{ State = $s; Detail = [string]$d } }
function Fit($s, $n) {
    $s = [string]$s
    if ($s.Length -gt $n) { $s.Substring(0, [math]::Max(0, $n - 1)) + $G.Ell } else { $s }
}
function Wipe { [Console]::BackgroundColor = [ConsoleColor]'Black'; [Console]::Clear() }

function Row {
    $len = 1
    Seg $G.V $script:BC
    Seg ' ' 'Gray'
    foreach ($x in $args) { Seg $x.T $x.F $x.B; $len += $x.T.Length }
    $pad = $W - $len
    if ($pad -gt 0) { Seg (' ' * $pad) 'Gray' }
    Seg $G.V $script:BC
    NL
}
function SepLine { Seg ($G.ML + ($G.H * $W) + $G.MR) $script:BC; NL }
# A status pill. With 24-bit color: colored text with a dot on a soft tint of the same color, instead of a solid block.
function Pill($txt, $fg, $bg) {
    if ($script:Vt -and $bg -and $Pal.ContainsKey('Soft' + $bg)) { return @((Pt (' ' + $G.Dot + ' ' + $txt.Trim() + ' ') $bg ('Soft' + $bg))) }
    @((Pt $txt $fg $bg))
}
function BannerRow($txt, $fg, $bg) {
    $parts = @(Pill $txt $fg $bg)
    $len = ($parts | ForEach-Object { $_.T.Length } | Measure-Object -Sum).Sum
    $l = [int][math]::Floor(($W - 1 - $len) / 2)
    Row (Pt (' ' * $l)) @parts
}
function BarRow($pct, $theme) {
    $n = 58
    $f = [int][math]::Floor($pct / 100 * $n)
    if ($f -gt $n) { $f = $n }
    $empty = 'DarkGray'; if ($script:Vt) { $empty = 'Faint' }
    Row (Pt '  ') (Pt ($G.Full * $f) $theme) (Pt ($G.Empty * ($n - $f)) $empty) (Pt ('  {0,3}%' -f [int]$pct) 'Gray')
}

# ------------------------- keyboard-navigation helpers -------------------------
function BarItem($k, $label, $color, $id) { [pscustomobject]@{ Key = $k; Label = $label; Color = $color; Id = $id } }
# a row of buttons: the highlighted one is chosen with the arrow keys + Enter (letters still work as shortcuts)
function ActionBar($items, $sel) {
    $parts = @(Pt ' ')
    for ($i = 0; $i -lt $items.Count; $i++) {
        $it = $items[$i]
        if ($script:Vt) {
            # monochrome buttons: the highlighted one is inverted; only a destructive action keeps its red
            if ($i -eq $sel) {
                if ($it.Color -eq 'Red') { $parts += (Pt ('  ' + $it.Label + '  ') 'Ink' 'DarkRed') }
                else { $parts += (Pt ('  ' + $it.Label + '  ') 'Black' 'White') }
            } else {
                $fg = 'Gray'; if ($it.Color -eq 'Red') { $fg = 'Red' }
                $parts += (Pt ('  ' + $it.Label + '  ') $fg)
            }
        } elseif ($i -eq $sel) {
            $fg = 'Black'; if ($it.Color -eq 'Red') { $fg = 'White' }
            $parts += (Pt ('  ' + $it.Label + '  ') $fg $it.Color)
        } else {
            $parts += (Pt ('  ' + $it.Label + '  ') $it.Color)
        }
        $parts += (Pt ' ')
    }
    Row @parts
}
function HintRow($t) { Row (Pt ('  ' + $t) 'DarkGray') }
function SubRow($t, $c) { Row (Pt ('     ' + $t) $c) }

# ---- outline around the selected option (unselected = same space left blank, so nothing jumps) ----
$BW = 69   # inner width of the outline
# outline color: quiet gray on 24-bit consoles, the classic cyan otherwise
function Edge { if ($script:Vt) { 'Gray' } else { 'Cyan' } }
function BoxTop($on) {
    if ($on) { Row (Pt ' ') (Pt ($G.RTL + ($G.RH * $BW) + $G.RTR) (Edge)) } else { Row }
}
function BoxBot($on) {
    if ($on) { Row (Pt ' ') (Pt ($G.RBL + ($G.RH * $BW) + $G.RBR) (Edge)) } else { Row }
}
function BoxRow($on) {
    $len = 0
    foreach ($x in $args) { $len += $x.T.Length }
    $pad = $BW - $len; if ($pad -lt 0) { $pad = 0 }
    $edge = ' '; if ($on) { $edge = $G.RV }
    $parts = @(Pt ' ')
    $parts += (Pt $edge (Edge))
    foreach ($x in $args) { $parts += $x }
    $parts += (Pt (' ' * $pad))
    $parts += (Pt $edge (Edge))
    Row @parts
}
function BSub($on, $t, $c) { BoxRow $on (Pt ('     ' + $t) $c) }
function OptRow($sel, $name, $desc) {
    $mk = '   '; $fg = 'White'; $bg = $null
    if ($sel) { $mk = ' ' + $G.Arrow + ' '; $fg = 'Black'; $bg = 'Cyan'; if ($script:Vt) { $bg = 'White' } }
    BoxRow $sel (Pt $mk (Edge)) (Pt (' ' + $name.PadRight(18) + ' ') $fg $bg) (Pt ('  ' + $desc) 'Gray')
}

try { Add-Type -Namespace Lsp -Name Win -MemberDefinition '[DllImport("kernel32.dll")] public static extern IntPtr GetConsoleWindow(); [DllImport("user32.dll")] public static extern bool IsIconic(IntPtr h);' } catch {}
try { Add-Type -Namespace Lsp -Name Pwr -MemberDefinition '[DllImport("kernel32.dll")] public static extern uint SetThreadExecutionState(uint f);' } catch {}
try { Add-Type -Namespace Lsp -Name Con -MemberDefinition '[DllImport("kernel32.dll")] public static extern IntPtr GetStdHandle(int n); [DllImport("kernel32.dll")] public static extern bool GetConsoleMode(IntPtr h, out uint m); [DllImport("kernel32.dll")] public static extern bool SetConsoleMode(IntPtr h, uint m);' } catch {}
# 24-bit color: turn on VT processing for the output (Windows 10+, Windows Terminal); plain ASCII mode keeps classic colors.
function Enable-Vt {
    if ($AsciiUi) { return $false }
    try {
        $h = [Lsp.Con]::GetStdHandle(-11)
        $m = [uint32]0
        if (-not [Lsp.Con]::GetConsoleMode($h, [ref]$m)) { return $false }
        if (($m -band 4) -ne 0) { return $true }
        return [Lsp.Con]::SetConsoleMode($h, [uint32]($m -bor 4))
    } catch { return $false }
}
$script:Vt = Enable-Vt
function Disable-QuickEdit {
    try {
        $h = [Lsp.Con]::GetStdHandle(-10)
        $m = [uint32]0
        if ([Lsp.Con]::GetConsoleMode($h, [ref]$m)) {
            if (($m -band 0x40) -ne 0) { $m = [uint32]($m - 0x40) }
            $m = [uint32]($m -bor 0x80)
            [void][Lsp.Con]::SetConsoleMode($h, $m)
        }
    } catch {}
}
function Test-Minimized {
    try { $h = [Lsp.Win]::GetConsoleWindow(); return (($h -ne [IntPtr]::Zero) -and [Lsp.Win]::IsIconic($h)) } catch { return $false }
}
$script:lastDraw = [datetime]::MinValue

function Frame($theme, $badge, [scriptblock]$body) {
    if (Test-Minimized) { return }   # never draw into a minimized console (it can stall)
    # 24-bit: a quiet gray frame whatever the state (the badge carries the color); classic: the frame takes the theme color
    $script:BC = $theme
    if ($script:Vt) { $script:BC = 'Line' }
    [Console]::CursorVisible = $false
    [Console]::SetCursorPosition(0, 0)
    Seg ($G.TL + ($G.H * $W) + $G.TR) $script:BC; NL
    Row
    # the logo fades from white to gray, top to bottom
    $shades = @('rgb:255;255;255', 'rgb:232;232;232', 'rgb:205;205;205', 'rgb:176;176;176', 'rgb:146;146;146', 'rgb:116;116;116')
    for ($li = 0; $li -lt $Logo.Count; $li++) {
        $lc = $theme; if ($script:Vt) { $lc = $shades[[math]::Min($li, $shades.Count - 1)] }
        Row (Pt ('  ' + $Logo[$li]) $lc)
    }
    Row
    $sub = 'AUTOMATED DEPLOYMENT SEQUENCE'
    $bp = @(Pill " $badge " 'Black' $theme)
    $sc = 'White'
    if ($script:Vt) { $sub = 'Local server launcher'; $sc = 'Gray' }
    $blen = ($bp | ForEach-Object { $_.T.Length } | Measure-Object -Sum).Sum
    $gap = $W - 4 - $sub.Length - $blen
    if ($gap -lt 1) { $gap = 1 }
    Row (Pt '  ') (Pt $sub $sc) (Pt (' ' * $gap)) @bp (Pt ' ')
    SepLine
    & $body
    Seg ($G.BL + ($G.H * $W) + $G.BR) $script:BC; NL
}

function StepRow($s, $theme) {
    # icon, name, detail and a right-hand tag (OK / WARN / FAIL); 'bg' = started, still finishing in the background
    $tag = '';  $t_c = 'DarkGray'
    switch ($s.State) {
        'pending' { $ic = $G.Pend;                  $ic_c = 'DarkGray'; $n_c = 'DarkGray'; $d_c = 'DarkGray' }
        'run'     { $ic = $SP[$script:spin % $SP.Count]; $ic_c = $theme;     $n_c = 'White';    $d_c = $theme;      $tag = ' RUN'; $t_c = $theme }
        'bg'      { $ic = $SP[$script:spin % $SP.Count]; $ic_c = 'DarkCyan'; $n_c = 'Gray';     $d_c = 'DarkCyan';  $tag = 'BUSY'; $t_c = 'DarkCyan' }
        'ok'      { $ic = $G.Ok;                    $ic_c = 'Green';    $n_c = 'White';    $d_c = 'DarkGray';  $tag = '  OK'; $t_c = 'Green' }
        'warn'    { $ic = $G.Warn;                  $ic_c = 'Yellow';   $n_c = 'Yellow';   $d_c = 'Yellow';    $tag = 'WARN'; $t_c = 'Yellow' }
        'fail'    { $ic = $G.Fail;                  $ic_c = 'Red';      $n_c = 'Red';      $d_c = 'Red';       $tag = 'FAIL'; $t_c = 'Red' }
    }
    Row (Pt '  ') (Pt $ic $ic_c) (Pt ' ') (Pt ((Fit $s.Name 27).PadRight(28)) $n_c) (Pt ((Fit $s.Detail 34).PadRight(35)) $d_c) (Pt $tag $t_c)
}

# --------------------------- loading-style screen ---------------------------
function Render-Steps($theme, $badge) {
    $total = $script:Steps.Count
    if ($total -lt 1) { return }
    $done = @($script:Steps | Where-Object { $_.State -in 'ok', 'warn', 'fail' }).Count
    $run  = @($script:Steps | Where-Object { $_.State -eq 'run' -or $_.State -eq 'bg' }).Count
    $target = [math]::Min(100, (($done + 0.5 * $run) / $total) * 100)
    if ($done -ge $total) { $target = 100 }
    $script:pct += ($target - $script:pct) * 0.3
    if ($target -ge 100 -and $script:pct -gt 98.5) { $script:pct = 100 }
    $cur = $script:Steps | Where-Object { $_.State -eq 'run' } | Select-Object -First 1
    $el  = ((Get-Date) - $script:T0).ToString('mm\:ss')
    Frame $theme $badge {
        Row
        foreach ($s in $script:Steps) { StepRow $s $theme }
        Row
        BarRow $script:pct $theme
        Row
        if ($script:Banner) {
            BannerRow $script:Banner.T $script:Banner.F $script:Banner.B
        } else {
            $msg = 'Standing by'
            if ($cur) { $msg = 'Running: ' + $cur.Name }
            Row (Pt '  ') (Pt ((Fit $msg 52).PadRight(52)) 'Gray') (Pt ('T+' + $el) 'DarkGray')
        }
    }
}
$script:Quiet = $false
function Pump {
    $script:spin++
    # background jobs (database start, asset build, services) flip their rows as soon as they finish
    if ($script:BgJobs.Count -gt 0 -and ((Get-Date) - $script:lastBgPoll).TotalMilliseconds -ge 400) {
        $script:lastBgPoll = Get-Date
        [void](Update-BgJobs)
    }
    if ($script:Quiet) { Start-Sleep -Milliseconds 60; return }
    if ($script:Prog) {
        # progress screen (download / update): the bar creeps toward the end of the current stage while a slow job runs
        $pg = $script:Prog
        if ($pg.Target -lt $pg.Hi) { $pg.Target = $pg.Target + ($pg.Hi - $pg.Target) * 0.004 }
        if (((Get-Date) - $script:lastDraw).TotalMilliseconds -ge 120) {
            Render-Prog
            $script:lastDraw = Get-Date
        }
        Start-Sleep -Milliseconds 60
        return
    }
    if (((Get-Date) - $script:lastDraw).TotalMilliseconds -ge 120) {
        Render-Steps $script:Theme $script:Badge
        $script:lastDraw = Get-Date
    }
    Start-Sleep -Milliseconds 60
}

# ------------------------- progress screen (download / update) -------------------------
$script:Prog = $null
$script:ProgQuiet = $false
function Render-Prog {
    $pg = $script:Prog
    if (-not $pg) { return }
    $pg.Disp = $pg.Disp + ($pg.Target - $pg.Disp) * 0.35
    if ([math]::Abs($pg.Target - $pg.Disp) -lt 0.5) { $pg.Disp = $pg.Target }
    $el = ((Get-Date) - $pg.T0).ToString('mm\:ss')
    Frame $pg.Theme $pg.Badge {
        Row
        Row (Pt ('  ' + (Fit $pg.Title 66)) 'White')
        Row
        Row (Pt '  ') (Pt ((Fit $pg.Msg 52).PadRight(52)) 'Gray') (Pt ('T+' + $el) 'DarkGray')
        Row
        BarRow $pg.Disp $pg.Theme
        Row
    }
}
function Prog-Begin($theme, $badge, $title) {
    $script:ProgQuiet = $script:Quiet
    $script:Quiet = $false
    $script:Prog = @{ Theme = $theme; Badge = $badge; Title = $title; Msg = ''; Target = 0.0; Disp = 0.0; Hi = 0.0; T0 = (Get-Date) }
    Wipe
}
# sets the message + percentage. $creepTo = where the bar may slowly crawl to while this stage is still working
function Prog-Stage($msg, $pct, $creepTo = -1) {
    if (-not $script:Prog) { return }
    $script:Prog.Msg = [string]$msg
    $script:Prog.Target = [double]$pct
    if ($creepTo -lt 0) { $creepTo = $pct }
    $script:Prog.Hi = [double]$creepTo
    for ($i = 0; $i -lt 5; $i++) { Pump }
}
function Prog-Hold($n = 12) { for ($i = 0; $i -lt $n; $i++) { Pump } }
function Prog-End {
    $script:Prog = $null
    $script:Quiet = $script:ProgQuiet
    Wipe
}

# ------------------------------ system helpers ------------------------------
function Test-Port($p, $addr = '127.0.0.1') {
    # fast loopback connect (Get-NetTCPConnection is slow and made waits crawl). $addr '::1' = the IPv6 loopback
    if ($addr -like '*:*') { $c = New-Object Net.Sockets.TcpClient([Net.Sockets.AddressFamily]::InterNetworkV6) } else { $c = New-Object Net.Sockets.TcpClient }
    $ok = $false
    try { $a = $c.BeginConnect($addr, [int]$p, $null, $null); $ok = ($a.AsyncWaitHandle.WaitOne(250) -and $c.Connected) } catch { $ok = $false }
    try { $c.Close() } catch {}
    [bool]$ok
}
# a Vite dev server started by hand may listen on 127.0.0.1 or, with host 'localhost', only on ::1 - both count
function Test-VitePort { (Test-Port $VitePort) -or (Test-Port $VitePort '::1') }
# the processes listening on a TCP port (any address)
function Get-PortOwners($p) {
    @(Get-NetTCPConnection -LocalPort ([int]$p) -State Listen -ErrorAction SilentlyContinue | ForEach-Object { [int]$_.OwningProcess } | Where-Object { $_ -gt 4 } | Select-Object -Unique)
}
# frees one of LSPDMS's own ports (web / live chat / Vite). Only PHP or Node programs are stopped there;
# anything else is left alone and reported. Returns '' when the port is free, else what is blocking it
function Clear-Port($p, $allowed = @('php', 'node')) {
    if (-not (Test-Port $p)) { return '' }
    $foreign = @()
    foreach ($id in (Get-PortOwners $p)) {
        $pr = Get-Process -Id $id -ErrorAction SilentlyContinue
        if (-not $pr) { continue }
        if ($allowed -contains ([string]$pr.ProcessName).ToLower()) { taskkill /F /T /PID $id *> $null }
        else { $foreign += ([string]$pr.ProcessName + ' (PID ' + $id + ')') }
    }
    if ($foreign.Count -gt 0) { return ('port ' + $p + ' is used by ' + ($foreign -join ', ')) }
    $t = Get-Date
    while ((Test-Port $p) -and ((Get-Date) - $t).TotalSeconds -lt 6) { Pump }
    if (Test-Port $p) { return ('port ' + $p + ' is still busy') }
    ''
}
function Start-Hidden($file, $arg, $log, $track = $false, $dir = $null) {
    $sp = @{ FilePath = $file; WindowStyle = 'Hidden'; PassThru = $true; ErrorAction = 'Stop' }
    if ($arg) { $sp.ArgumentList = $arg }
    if ($dir) { $sp.WorkingDirectory = $dir } elseif (Test-Path -LiteralPath $Project) { $sp.WorkingDirectory = $Project }
    if ($log) { $sp.RedirectStandardOutput = $log; $sp.RedirectStandardError = "$log.err" }
    $p = Start-Process @sp
    $null = $p.Handle
    if ($track) { Add-Tracked $p 'proc' }
    $p
}

# ---- processes the launcher started. Saved to storage\app\launcher\pids.json (PID + start time), so a later run,
#      "Shut down" or "LSPDMS_Launcher.bat stop" ends exactly these and never another program that reused the PID ----
$script:ProcNames = @{}
function Get-PidFile { Join-Path $StateDir 'pids.json' }
function Read-PidFile {
    $r = @()
    try {
        $pf = Get-PidFile
        if (Test-Path -LiteralPath $pf) {
            $o = [IO.File]::ReadAllText($pf) | ConvertFrom-Json
            foreach ($e in $o) { if ($e -and $e.pid) { $r += $e } }
        }
    } catch {}
    $r
}
function Save-PidFile {
    $list = @()
    foreach ($p in $script:Procs) {
        try {
            if (-not $p.HasExited) { $list += [pscustomobject]@{ name = [string]$script:ProcNames[[string]$p.Id]; pid = $p.Id; started = $p.StartTime.ToUniversalTime().Ticks } }
        } catch {}
    }
    $pf = Get-PidFile
    try {
        if ($list.Count -eq 0) { Remove-Item -LiteralPath $pf -Force -ErrorAction SilentlyContinue; return }
        New-Item -ItemType Directory -Force (Split-Path -Parent $pf) | Out-Null
        [IO.File]::WriteAllText($pf, (ConvertTo-Json -InputObject @($list) -Depth 3), (New-Object Text.UTF8Encoding($false)))
    } catch {}
}
function Add-Tracked($p, $name) {
    if (-not $p) { return }
    $script:Procs = @($script:Procs | Where-Object { $_.Id -ne $p.Id }) + @($p)
    $script:ProcNames[[string]$p.Id] = [string]$name
    Save-PidFile
}
# the live process for a pids.json entry, or $null when it ended or the PID now belongs to another program
function Get-RecordedProc($e) {
    try {
        $p = Get-Process -Id ([int]$e.pid) -ErrorAction Stop
        if ([math]::Abs($p.StartTime.ToUniversalTime().Ticks - [long]$e.started) -lt 20000000) { return $p }
    } catch {}
    $null
}
# the running process the launcher started under this name (this run or an earlier one), or $null
function Get-TrackedProc($name) {
    foreach ($p in $script:Procs) {
        try { if ([string]$script:ProcNames[[string]$p.Id] -eq $name -and -not $p.HasExited) { return $p } } catch {}
    }
    $null
}
# earlier runs: keep (adopt) the processes named in $adopt, stop every other one still alive. Returns how many were stopped
function Resolve-PidFile($adopt = @()) {
    $n = 0
    foreach ($e in (Read-PidFile)) {
        $rp = Get-RecordedProc $e
        if (-not $rp) { continue }
        if (@($script:Procs | Where-Object { $_.Id -eq $rp.Id }).Count -gt 0) { continue }
        if ($adopt -contains [string]$e.name) {
            $null = $rp.Handle
            $script:Procs += $rp; $script:ProcNames[[string]$rp.Id] = [string]$e.name
            if ([string]$e.name -eq 'mysql') { $script:StartedMySQL = $true }
        } else {
            taskkill /F /T /PID $rp.Id *> $null; $n++
        }
    }
    Save-PidFile
    $n
}
# stops what the launcher started (now or in an earlier run), except the names in $keep
function Stop-Tracked($keep = @()) {
    [void](Resolve-PidFile $keep)
    $left = @()
    foreach ($p in $script:Procs) {
        $nm = [string]$script:ProcNames[[string]$p.Id]
        if ($keep -contains $nm) { $left += $p; continue }
        try { if (-not $p.HasExited) { taskkill /F /T /PID $p.Id *> $null } } catch {}
        if ($nm -eq 'mysql') { $script:StartedMySQL = $false }
    }
    $script:Procs = $left
    Save-PidFile
}
# starts a background service and remembers it. The previous log is kept as *.prev.log
function Start-Tracked($name, $file, $arg, $log, $dir = $null) {
    if ($log) {
        foreach ($x in @($log, "$log.err")) {
            if (Test-Path -LiteralPath $x) { Move-Item -LiteralPath $x -Destination ($x -replace '\.log(\.err)?$', '.prev.log$1') -Force -ErrorAction SilentlyContinue }
        }
    }
    $p = Start-Hidden $file $arg $log $false $dir
    Add-Tracked $p $name
    $p
}
function Get-OpenUrl { if ($LanUrl) { $LanUrl } else { $Url } }
function Test-Admin {
    ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}
function Wait-Proc($p, $max = 0) {
    $t = Get-Date
    while (-not $p.HasExited) {
        if ($max -gt 0 -and ((Get-Date) - $t).TotalSeconds -gt $max) { try { $p.Kill() } catch {}; break }
        Pump
    }
    [void]$p.WaitForExit(1000)
}
function Wait-Port($port, $sec) {
    $t = Get-Date
    while (((Get-Date) - $t).TotalSeconds -lt $sec) {
        if (Test-Port $port) { return $true }
        Pump
    }
    return (Test-Port $port)
}
function Get-LogTail($base, $n) {
    $lines = @()
    foreach ($f in @($base, "$base.err")) {
        if ($f -and (Test-Path -LiteralPath $f)) { $lines += @(Get-Content -LiteralPath $f -Tail 400) }
    }
    $clean = @()
    foreach ($l in $lines) {
        $c = ([string]$l) -replace '\x1b\[[0-9;]*[A-Za-z]', '' -replace "`t", '  ' -replace '[^\x20-\x7E]', ''
        if ($c.Trim().Length -gt 0) { $clean += $c }
    }
    if ($clean.Count -gt $n) { $clean = $clean[($clean.Count - $n)..($clean.Count - 1)] }
    $clean
}
# stops the launcher's process(es) with this name (web, reverb, scheduler, queue, vite, mysql)
function Stop-TrackedName($name) {
    $left = @()
    foreach ($p in $script:Procs) {
        if ([string]$script:ProcNames[[string]$p.Id] -eq $name) { try { if (-not $p.HasExited) { taskkill /F /T /PID $p.Id *> $null } } catch {} }
        else { $left += $p }
    }
    $script:Procs = $left
    Save-PidFile
}
function Stop-All {
    # Restart / Retry: stop what the launcher started. A database it started keeps running (faster restart)
    Stop-Tracked @('mysql')
}

# ------------------------ portability + network repair ------------------------
function Find-Laragon {
    foreach ($r in 'C:\laragon', 'D:\laragon', 'E:\laragon', "$env:SystemDrive\laragon") { if (Test-Path $r) { return $r } }
    return $null
}
# the web folder of Laragon. The project lives here and nowhere else (no XAMPP / htdocs)
function Get-WwwRoot {
    $lar = Find-Laragon
    if ($lar) { return "$lar\www" }
    return 'C:\laragon\www'
}
# true only when the path is a folder INSIDE <laragon>\www
function Test-InWww($p) {
    try {
        $www  = ([IO.Path]::GetFullPath((Get-WwwRoot))).TrimEnd('\') + '\'
        $full = ([IO.Path]::GetFullPath([string]$p)).TrimEnd('\') + '\'
        return ($full.StartsWith($www, [StringComparison]::OrdinalIgnoreCase) -and $full.Length -gt $www.Length)
    } catch { return $false }
}
# the installed MySQL / MariaDB Windows service (e.g. XAMPP's 'mysql'), or $null. When one exists it owns the live data:
# the launcher starts / waits for THAT service and never boots a second mysqld with another data folder
function Get-DbService {
    $all = @(Get-CimInstance Win32_Service -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '^(mysql|mariadb)' -or [string]$_.PathName -match '(?i)\\(mysqld|mariadbd)(-nt)?\.exe' })
    $all | Sort-Object { if ($_.State -eq 'Running') { 0 } elseif ($_.StartMode -ne 'Disabled') { 1 } else { 2 } } | Select-Object -First 1
}
function Find-MySqld {
    $c = @()
    $svc = Get-DbService
    if ($svc) {
        $pn = [string]$svc.PathName
        $em = [regex]::Match($pn, '^\s*"([^"]+\.exe)"|^\s*(\S+\.exe)', 'IgnoreCase')
        $cm = [regex]::Match($pn, '--defaults-file="?([^"]+?)"?(\s|$)', 'IgnoreCase')
        if ($em.Success) {
            $exe = $em.Groups[1].Value; if (-not $exe) { $exe = $em.Groups[2].Value }
            $cfg = ''; if ($cm.Success) { $cfg = $cm.Groups[1].Value }
            $c += @{ Exe = $exe; Cfg = $cfg; Service = [string]$svc.Name }
        }
    }
    if ($MySQLHome) { $c += @{ Exe = (Join-Path $MySQLHome 'bin\mysqld.exe'); Cfg = (Join-Path $MySQLHome 'bin\my.ini') } }
    $dirs = @(Get-ChildItem $MySQLRoot -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending)
    foreach ($d in $dirs) { $c += @{ Exe = "$($d.FullName)\bin\mysqld.exe"; Cfg = "$($d.FullName)\my.ini" } }
    foreach ($x in $c) { if (Test-Path $x.Exe) { return $x } }
    return $null
}
function Resolve-Project {
    # Laragon only: the project must be a folder inside <laragon>\www
    $www  = Get-WwwRoot
    $leaf = Split-Path -Leaf $Project
    $c = @(Join-Path $www $leaf)
    if (Test-Path $www) {
        $dirs = @(Get-ChildItem $www -Directory -ErrorAction SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName 'artisan') } | Sort-Object { if ($_.Name -match 'LSPDMS') { 0 } else { 1 } })
        foreach ($d in $dirs) { $c += $d.FullName }
    }
    foreach ($x in $c) { if ($x -and (Test-Path (Join-Path $x 'artisan'))) { return $x } }
    # not on this device yet: the download (and every later update) goes to <laragon>\www\<project folder>
    return (Join-Path $www $leaf)
}
function Add-ToolPaths {
    $adds = @()
    $lar = Find-Laragon
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
        if ($lar) {
            $d = Get-ChildItem "$lar\bin\php" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
            if ($d -and (Test-Path "$($d.FullName)\php.exe")) { $adds += $d.FullName }
        }
    }
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
        if ($lar) {
            $d = Get-ChildItem "$lar\bin\nodejs" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
            if ($d) { $adds += $d.FullName }
        }
        $adds += "$env:ProgramFiles\nodejs"
    }
    foreach ($a in $adds) { if (Test-Path $a) { $env:Path = $env:Path + ';' + $a } }
    if (-not (Test-Path $MySQLRoot) -and $lar -and (Test-Path "$lar\bin\mysql")) { $script:MySQLRoot = "$lar\bin\mysql" }
}
function Sync-Path {
    # pick up programs installed during this session (winget changes the system PATH)
    $m = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $u = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = $m + ';' + $u + ';' + $env:Path
}
function Get-ComposerCmd {
    if (Get-Command composer -ErrorAction SilentlyContinue) { return 'composer' }
    $own = Join-Path $ToolsDir 'composer.phar'
    if (Test-Path $own) { return ('php "' + $own + '"') }
    $lar = Find-Laragon
    if ($lar) {
        $ph = Get-ChildItem "$lar\bin\composer" -Filter 'composer.phar' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($ph) { return ('php "' + $ph.FullName + '"') }
    }
    return $null
}
function Get-WorkAdapter {
    $bad = 'WARP|VPN|vEthernet|Loopback|VirtualBox|VMware|Hyper-V|Bluetooth|Docker|WSL|TAP|Tailscale|ZeroTier|Host-Only'
    $ad = @(Get-NetAdapter -Physical -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' -and $_.InterfaceDescription -notmatch $bad -and $_.Name -notmatch $bad })
    if ($ad.Count -eq 0) { return $null }
    $apipa = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -like '169.254.*' } | ForEach-Object { $_.InterfaceIndex })
    $pick = $ad | Where-Object { $apipa -contains $_.ifIndex } | Select-Object -First 1
    if (-not $pick) { $pick = $ad | Select-Object -First 1 }
    $pick
}
function Restore-Network {
    if (-not (Test-Admin)) { return }
    if ($script:StaticIf) {
        $i = [int]$script:StaticIf
        Get-NetIPAddress -InterfaceIndex $i -AddressFamily IPv4 -ErrorAction SilentlyContinue | Remove-NetIPAddress -Confirm:$false -ErrorAction SilentlyContinue
        Set-NetIPInterface -InterfaceIndex $i -Dhcp Enabled -ErrorAction SilentlyContinue
        Set-DnsClientServerAddress -InterfaceIndex $i -ResetServerAddresses -ErrorAction SilentlyContinue
        $ad = Get-NetAdapter -InterfaceIndex $i -ErrorAction SilentlyContinue
        if ($ad) { Start-Process cmd.exe -ArgumentList ('/c ipconfig /renew "' + $ad.Name + '"') -WindowStyle Hidden }
        $script:StaticIf = $null
    }
    Remove-Item -LiteralPath $NetStateFile -Force -ErrorAction SilentlyContinue
}
function Repair-Network {
    $ad = Get-WorkAdapter
    if (-not $ad) { return $null }
    # 0) a manual (static) IP that is unusable (duplicate / wrong network): go back to automatic (DHCP)
    $ifc = Get-NetIPInterface -InterfaceIndex $ad.ifIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue
    if ($ifc -and $ifc.Dhcp -eq 'Disabled' -and -not $script:StaticIf) {
        Get-NetIPAddress -InterfaceIndex $ad.ifIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.PrefixOrigin -eq 'Manual' } | Remove-NetIPAddress -Confirm:$false -ErrorAction SilentlyContinue
        Remove-NetRoute -InterfaceIndex $ad.ifIndex -DestinationPrefix '0.0.0.0/0' -Confirm:$false -ErrorAction SilentlyContinue
        Set-NetIPInterface -InterfaceIndex $ad.ifIndex -Dhcp Enabled -ErrorAction SilentlyContinue
        Set-DnsClientServerAddress -InterfaceIndex $ad.ifIndex -ResetServerAddresses -ErrorAction SilentlyContinue
    }
    # 1) release + renew on this adapter, poll for a new dynamic address
    $p = Start-Hidden 'cmd.exe' ('/c ipconfig /release "' + $ad.Name + '"') "$Logs\ip-release.log"; Wait-Proc $p 15
    $p = Start-Hidden 'cmd.exe' ('/c ipconfig /renew "' + $ad.Name + '"') "$Logs\ip-renew.log"; Wait-Proc $p 25
    $t = Get-Date
    while (((Get-Date) - $t).TotalSeconds -lt 8) {
        $ip = Get-LanIp
        if ($ip) { $script:NetNote = ' (renewed)'; return $ip }
        Pump
    }
    # 2) reconnect the adapter (re-joins Wi-Fi / re-asks the router), wait up to ~25s, renew again
    $p = Start-Hidden 'powershell.exe' ('-NoProfile -Command Restart-NetAdapter -Name "' + $ad.Name + '" -Confirm:$false') "$Logs\ip-restart.log"; Wait-Proc $p 40
    $t = Get-Date; $renewed = $false
    while (((Get-Date) - $t).TotalSeconds -lt 25) {
        $ip = Get-LanIp
        if ($ip) { $script:NetNote = ' (reconnected)'; return $ip }
        if (-not $renewed -and ((Get-Date) - $t).TotalSeconds -gt 10) {
            $renewed = $true
            $p = Start-Hidden 'cmd.exe' ('/c ipconfig /renew "' + $ad.Name + '"') "$Logs\ip-renew2.log"; Wait-Proc $p 15
        }
        Pump
    }
    if (-not $AllowFixedFallback) { return $null }
    # 3) router never answered: temporary fixed address on the router's own subnet (undone on shutdown)
    $gw = $null
    $cfg = Get-NetIPConfiguration -InterfaceIndex $ad.ifIndex -ErrorAction SilentlyContinue
    if ($cfg -and $cfg.IPv4DefaultGateway) { $gw = [string]$cfg.IPv4DefaultGateway[0].NextHop }
    $parts = $FallbackIp.Split('.')
    $base = ($parts[0..2] -join '.'); $first = [int]$parts[3]
    if ($gw -match '^(\d+\.\d+\.\d+)\.(\d+)$') { $base = $Matches[1]; $gwLast = [int]$Matches[2] } else { $gwLast = -1 }
    $cands = @($first..($first + 4)) + @(240..243) | Where-Object { $_ -ne $gwLast }
    foreach ($oct in $cands) {
        $cand = "$base.$oct"
        Set-NetIPInterface -InterfaceIndex $ad.ifIndex -Dhcp Disabled -ErrorAction SilentlyContinue
        Get-NetIPAddress -InterfaceIndex $ad.ifIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue | Remove-NetIPAddress -Confirm:$false -ErrorAction SilentlyContinue
        Remove-NetRoute -InterfaceIndex $ad.ifIndex -DestinationPrefix '0.0.0.0/0' -Confirm:$false -ErrorAction SilentlyContinue
        if ($gw) { New-NetIPAddress -InterfaceIndex $ad.ifIndex -IPAddress $cand -PrefixLength 24 -DefaultGateway $gw -ErrorAction SilentlyContinue | Out-Null }
        else     { New-NetIPAddress -InterfaceIndex $ad.ifIndex -IPAddress $cand -PrefixLength 24 -ErrorAction SilentlyContinue | Out-Null }
        if ($gw) { Set-DnsClientServerAddress -InterfaceIndex $ad.ifIndex -ServerAddresses @($gw, '8.8.8.8') -ErrorAction SilentlyContinue }
        $script:StaticIf = $ad.ifIndex
        try { Set-Content -LiteralPath $NetStateFile -Value $ad.ifIndex -ErrorAction Stop } catch {}
        $t = Get-Date
        while (((Get-Date) - $t).TotalSeconds -lt 3) { Pump }
        $st = (Get-NetIPAddress -IPAddress $cand -ErrorAction SilentlyContinue).AddressState
        if ($st -eq 'Preferred') { $script:NetNote = ' (fixed)'; return $cand }
    }
    Restore-Network
    return $null
}

function Get-EnvValue($key) {
    $envf = "$Project\.env"
    if (-not (Test-Path $envf)) { return '' }
    $txt = [string](Get-Content $envf -Raw)
    $m = [regex]::Match($txt, '(?m)^[ \t]*' + [regex]::Escape($key) + '[ \t]*=[ \t]*([^\r\n]*?)[ \t\r]*$')
    if (-not $m.Success) { return '' }
    $v = $m.Groups[1].Value.Trim()
    if ($v.StartsWith('"') -or $v.StartsWith("'")) {
        if ($v.Length -ge 2 -and $v.EndsWith($v.Substring(0, 1))) { $v = $v.Substring(1, $v.Length - 2) }
    } else { $v = ($v -replace '\s+#.*$', '') }
    return $v
}

# sets KEY=value in .env text: replaces the active line, else a commented one, else appends it
function Set-EnvLine($txt, $key, $val, $nl) {
    $k = [regex]::Escape($key)
    $rep = $key + '=' + $val
    $re = New-Object Text.RegularExpressions.Regex('(?m)^[ \t]*' + $k + '[ \t]*=[^\r\n]*')
    if ($re.IsMatch($txt)) { return $re.Replace($txt, $rep, 1) }
    $re = New-Object Text.RegularExpressions.Regex('(?m)^[ \t]*#[ \t]*' + $k + '[ \t]*=[^\r\n]*')
    if ($re.IsMatch($txt)) { return $re.Replace($txt, $rep, 1) }
    return ($txt.TrimEnd() + $nl + $rep + $nl)
}

# .env must never point at XAMPP: rewrite any xampp\htdocs / xampp\mysql\bin / xampp\php path to its Laragon equivalent (runs on every launch)
function Fix-EnvPaths {
    $envf = "$Project\.env"
    if (-not (Test-Path $envf)) { return $null }
    $txt = [string](Get-Content $envf -Raw)
    if ($txt -notmatch '(?i)xampp|htdocs') { return $null }
    $new = $txt
    $www = Get-WwwRoot
    $new = $new -replace '(?i)[A-Za-z]:\\+xampp\\+htdocs', $www
    $new = $new -replace '(?i)[A-Za-z]:/+xampp/+htdocs', ($www -replace '\\', '/')
    $m = Find-MySqld
    if ($m) {
        $bin = Split-Path -Parent $m.Exe
        $new = $new -replace '(?i)[A-Za-z]:\\+xampp\\+mysql\\+bin', $bin
        $new = $new -replace '(?i)[A-Za-z]:/+xampp/+mysql/+bin', ($bin -replace '\\', '/')
    }
    $lar = Find-Laragon
    if ($lar) {
        $pd = Get-ChildItem "$lar\bin\php" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
        if ($pd) {
            $new = $new -replace '(?im)[A-Za-z]:\\+xampp\\+php(?=[\\/\s"'']|$)', $pd.FullName
            $new = $new -replace '(?im)[A-Za-z]:/+xampp/+php(?=[\\/\s"'']|$)', ($pd.FullName -replace '\\', '/')
        }
    }
    if ($new -eq $txt) { return $null }
    try {
        [IO.File]::WriteAllText($envf, $new, (New-Object Text.UTF8Encoding($false)))
        return 'changed XAMPP paths in .env to Laragon'
    } catch { return $null }
}

# .env is not on GitHub, so a new PC gets Laravel's default (SQLite). Switch it to MySQL so the app and the master-account check use the real database
function Fix-EnvDatabase {
    [void](Fix-EnvPaths)   # XAMPP/htdocs paths -> Laragon, always
    if (-not $AutoFixDbEnv) { return $null }
    $envf = "$Project\.env"
    if (-not (Test-Path $envf)) { return $null }
    $conn = (Get-EnvValue 'DB_CONNECTION').ToLower()
    if ($conn -eq 'mysql' -or $conn -eq 'mariadb') { return $null }
    $txt = [string](Get-Content $envf -Raw)
    $nl = "`n"; if ($txt -match "`r`n") { $nl = "`r`n" }
    $txt = Set-EnvLine $txt 'DB_CONNECTION' 'mysql' $nl
    if (-not (Get-EnvValue 'DB_HOST'))     { $txt = Set-EnvLine $txt 'DB_HOST' '127.0.0.1' $nl }
    if (-not (Get-EnvValue 'DB_PORT'))     { $txt = Set-EnvLine $txt 'DB_PORT' '3306' $nl }
    if (-not (Get-EnvValue 'DB_USERNAME')) { $txt = Set-EnvLine $txt 'DB_USERNAME' 'root' $nl }
    $dn = Get-EnvValue 'DB_DATABASE'
    if ((-not $dn) -or $dn -eq 'laravel' -or $dn -match '[\\/]|\.sqlite') { $txt = Set-EnvLine $txt 'DB_DATABASE' $DbName $nl }
    try {
        [IO.File]::WriteAllText($envf, $txt, (New-Object Text.UTF8Encoding($false)))
        return 'switched .env to MySQL'
    } catch { return $null }
}

# make sure the MySQL database named in .env exists (migrate cannot create it by itself)
function Ensure-Database {
    $conn = (Get-EnvValue 'DB_CONNECTION').ToLower()
    if ($conn -ne 'mysql' -and $conn -ne 'mariadb') { return $null }
    $name = Get-EnvValue 'DB_DATABASE'
    if (-not $name) { return $null }
    $dbh = Get-EnvValue 'DB_HOST'; if (-not $dbh) { $dbh = '127.0.0.1' }
    $dbp = Get-EnvValue 'DB_PORT'; if (-not $dbp) { $dbp = '3306' }
    $env:LSP_H = $dbh; $env:LSP_P = $dbp; $env:LSP_U = (Get-EnvValue 'DB_USERNAME'); $env:LSP_W = (Get-EnvValue 'DB_PASSWORD'); $env:LSP_D = $name
    $code = @(
        '<?php',
        '$h = getenv("LSP_H"); $p = getenv("LSP_P"); $u = getenv("LSP_U"); $w = getenv("LSP_W"); $d = str_replace("`", "", getenv("LSP_D"));',
        'try {',
        '  $pdo = new PDO("mysql:host=$h;port=$p", $u, $w);',
        '  $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $d . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");',
        '  echo "OK";',
        '} catch (Throwable $e) { echo "ERR: " . $e->getMessage(); exit(1); }'
    ) -join "`n"
    $php = "$Logs\mkdb.php"
    Set-Content -Path $php -Value $code -Encoding ASCII
    $p = Start-Hidden 'php' ('"' + $php + '"') "$Logs\mkdb.log"
    Wait-Proc $p 30
    $out = ([string](Get-Content "$Logs\mkdb.log" -Raw)).Trim()
    foreach ($n in 'LSP_H', 'LSP_P', 'LSP_U', 'LSP_W', 'LSP_D') { Remove-Item "Env:\$n" -ErrorAction SilentlyContinue }
    return $out
}

# create the database if needed, then php artisan migrate (adds missing tables only - never deletes data).
# NEW migrations are only run after a mysqldump backup succeeded (storage\app\manual-backups\pre-migrate-*.sql)
function Invoke-Migrate {
    if (-not (Test-Path -LiteralPath (Join-Path $Project 'artisan'))) { return (Res 'warn' 'project not found') }
    $mk = Ensure-Database
    if ($mk -and $mk -match '^ERR') {
        $script:FailLog = Join-Path $Logs 'mkdb.log'
        return (Res 'warn' 'cannot reach MySQL / create DB')
    }
    $pend = Get-PendingMigrations
    if ($pend.State -eq 'none') { return (Res 'ok' 'schema already up to date') }
    $bk = ''
    if ($BackupBeforeMigrate -and $pend.State -ne 'fresh') {
        $b = Backup-Database 'pre-migrate'
        if ($b.State -ne 'ok') {
            $what = 'new migrations'
            if ($pend.Count -gt 0) { $what = [string]$pend.Count + ' new migration(s)' }
            Write-LaunchLog ('backup failed (' + $b.Detail + ') - ' + $what + ' NOT run')
            return (Res 'warn' ('backup failed - ' + $what + ' not run'))
        }
        $bk = 'backup saved, '
        Write-LaunchLog ('backup before migrate: ' + $script:LastBackup)
    }
    $log = Join-Path $Logs 'migrate.log'
    $p = Start-Hidden 'php' 'artisan migrate --force --no-interaction' $log
    Wait-Proc $p 300
    $txt = (@(Get-LogTail $log 40)) -join "`n"
    if ($null -eq $p.ExitCode -or $p.ExitCode -eq 0) {
        if ($txt -match 'Nothing to migrate') { return (Res 'ok' 'schema already up to date') }
        if ($pend.Count -gt 0) { return (Res 'ok' ($bk + [string]$pend.Count + ' migration(s) applied')) }
        return (Res 'ok' ($bk + 'tables created / updated'))
    }
    $script:FailLog = $log
    $me = @(@(Get-LogTail $log 40) | Where-Object { $_ -match 'SQLSTATE|Exception|Error|could not find driver|Access denied' } | Select-Object -First 1)
    if ($me.Count -gt 0) {
        $mm = ([string]$me[0] -replace '^\s*(Illuminate\\[A-Za-z\\]+\\)?(QueryException|PDOException)?\s*', '').Trim()
        return (Res 'warn' ('migrate failed: ' + (Fit $mm 44)))
    }
    Res 'warn' 'migrate failed - see migrate.log'
}

# =========================== launch helpers (Oct 2026) ===========================
# logs + state move into the project once it exists: storage\logs\launcher and storage\app\launcher (both git-ignored)
function Set-LauncherPaths {
    if (Test-Path -LiteralPath (Join-Path $Project 'storage')) {
        $script:Logs = Join-Path $Project 'storage\logs\launcher'
        $script:StateDir = Join-Path $Project 'storage\app\launcher'
    }
    try { New-Item -ItemType Directory -Force $script:Logs, $script:StateDir | Out-Null } catch {}
}
# one line per step in storage\logs\launcher\launcher-YYYY-MM.log (step details never contain passwords or keys)
function Write-LaunchLog($text) {
    try {
        $lf = Join-Path $Logs ('launcher-' + (Get-Date -Format 'yyyy-MM') + '.log')
        Add-Content -LiteralPath $lf -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + [string]$text) -Encoding UTF8
    } catch {}
}
# small JSON notes the launcher keeps between runs (package lock hashes, build settings hash)
function Read-State($name) {
    try {
        $sf = Join-Path $StateDir ($name + '.json')
        if (Test-Path -LiteralPath $sf) { return ([IO.File]::ReadAllText($sf) | ConvertFrom-Json) }
    } catch {}
    $null
}
function Save-State($name, $obj) {
    try {
        New-Item -ItemType Directory -Force $StateDir | Out-Null
        [IO.File]::WriteAllText((Join-Path $StateDir ($name + '.json')), (ConvertTo-Json -InputObject $obj -Depth 4), (New-Object Text.UTF8Encoding($false)))
    } catch {}
}
function Get-StateValue($obj, $key) {
    if ($obj -and $obj.PSObject.Properties[$key]) { return [string]$obj.$key }
    ''
}
function Get-FileSha($path) {
    if (-not (Test-Path -LiteralPath $path)) { return '' }
    try { return (Get-FileHash -LiteralPath $path -Algorithm SHA256 -ErrorAction Stop).Hash } catch { return '' }
}
function Get-TextSha($t) {
    $h = [Security.Cryptography.SHA256]::Create()
    try { (($h.ComputeHash([Text.Encoding]::UTF8.GetBytes([string]$t)) | ForEach-Object { $_.ToString('x2') }) -join '') } finally { $h.Dispose() }
}

# ---- background jobs: a step can start something slow and return 'bg'; its row turns OK / WARN / FAIL when it is done ----
# $Check is called as  & $Check $job $final  and returns $null while still busy (with $final = $true it must return a result)
$script:BgJobs = @()
$script:InBgPoll = $false
$script:lastBgPoll = [datetime]::MinValue
function Add-BgJob($name, $step, $timeoutSec, [scriptblock]$check, $data = @{}) {
    $script:BgJobs += @{ Name = $name; Step = $step; T0 = (Get-Date); Timeout = $timeoutSec; Check = $check; Data = $data }
}
function Complete-BgJob($j, $r) {
    if ($j.Step) {
        $j.Step.State = $r.State; $j.Step.Detail = $r.Detail
        Write-LaunchLog ('[' + ([string]$r.State).ToUpper() + '] ' + $j.Step.Name + ' - ' + $r.Detail)
    }
}
# one non-blocking pass over the jobs. Returns how many of the named ones (or of all) are still running
function Update-BgJobs($names = $null) {
    if ($script:InBgPoll) { return 1 }
    $script:InBgPoll = $true
    $busy = 0
    try {
        $left = @()
        foreach ($j in $script:BgJobs) {
            $mine = (-not $names) -or ($names -contains $j.Name)
            $r = $null
            try { $r = & $j.Check $j $false } catch { $r = Res 'warn' $_.Exception.Message }
            if (-not $r -and ((Get-Date) - $j.T0).TotalSeconds -ge $j.Timeout) {
                try { $r = & $j.Check $j $true } catch { $r = $null }
                if (-not $r) { $r = Res 'warn' 'timed out' }
            }
            if ($r) { Complete-BgJob $j $r } else { $left += $j; if ($mine) { $busy++ } }
        }
        $script:BgJobs = $left
    } finally { $script:InBgPoll = $false }
    $busy
}
function Wait-BgJobs($names = $null) {
    while ((Update-BgJobs $names) -gt 0) { Pump }
}

# ---- .env: ports, Reverb keys ----
function Update-PortsFromEnv {
    foreach ($k in 'REVERB_SERVER_PORT', 'REVERB_PORT') {
        $v = Get-EnvValue $k
        if ($v -match '^\d{2,5}$') { $script:ReverbPort = [int]$v; break }
    }
    $v = Get-EnvValue 'DB_PORT'
    if ($v -match '^\d{2,5}$') { $script:MySQLPort = [int]$v }
    $bc = (Get-EnvValue 'BROADCAST_CONNECTION').ToLower()
    $script:UseReverb = ($UseReverbSetting -and (-not $bc -or $bc -eq 'reverb'))
}
# live chat needs a key + secret. A new PC still has a placeholder: make unique ones (written to .env, never shown)
function Ensure-ReverbKeys {
    if (-not $UseReverb) { return '' }
    $envf = Join-Path $Project '.env'
    if (-not (Test-Path -LiteralPath $envf)) { return '' }
    $k = Get-EnvValue 'REVERB_APP_KEY'
    $sec = Get-EnvValue 'REVERB_APP_SECRET'
    $ph = @('', 'local', 'your-app-key', 'your-app-secret', 'app-key', 'app-secret', 'null')
    if (($ph -notcontains $k.ToLower()) -and ($ph -notcontains $sec.ToLower())) { return '' }
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $kb = New-Object byte[] 16; $sb = New-Object byte[] 24; $ib = New-Object byte[] 4
        $rng.GetBytes($kb); $rng.GetBytes($sb); $rng.GetBytes($ib)
    } finally { $rng.Dispose() }
    $txt = [IO.File]::ReadAllText($envf)
    $nl = "`n"; if ($txt -match "`r`n") { $nl = "`r`n" }
    $txt = Set-EnvLine $txt 'REVERB_APP_ID' ([string](100000 + ([BitConverter]::ToUInt32($ib, 0) % 900000))) $nl
    $txt = Set-EnvLine $txt 'REVERB_APP_KEY' ((($kb | ForEach-Object { $_.ToString('x2') }) -join '')) $nl
    $txt = Set-EnvLine $txt 'REVERB_APP_SECRET' ((($sb | ForEach-Object { $_.ToString('x2') }) -join '')) $nl
    try { [IO.File]::WriteAllText($envf, $txt, (New-Object Text.UTF8Encoding($false))); return 'new live chat keys' } catch { return '' }
}
# a fresh copy can miss these folders; Laravel cannot write views / sessions / cache without them
function Ensure-StorageDirs {
    foreach ($d in 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'storage\app\public', 'bootstrap\cache') {
        $x = Join-Path $Project $d
        if (-not (Test-Path -LiteralPath $x)) { try { New-Item -ItemType Directory -Force $x | Out-Null } catch {} }
    }
}
function Invoke-EnvCheck {
    $envf = Join-Path $Project '.env'
    if (-not (Test-Path -LiteralPath $envf) -or ([IO.File]::ReadAllText($envf) -notmatch '(?m)^APP_KEY=\S+')) {
        $r = Get-LastRes (Install-Env)
        if (-not $r -or $r.State -eq 'fail') { return (Res 'fail' '.env / APP_KEY missing - open Dependencies') }
    }
    Ensure-StorageDirs
    $notes = @(); $warns = @()
    $rk = Ensure-ReverbKeys
    Save-EnvKeep
    if ($rk) { $notes += $rk }
    # public\storage -> storage\app\public (profile pictures)
    if (-not (Test-Path -LiteralPath (Join-Path $Project 'public\storage'))) {
        $p = Start-Hidden 'php' 'artisan storage:link --no-interaction' (Join-Path $Logs 'storage-link.log')
        Wait-Proc $p 60
        if (Test-Path -LiteralPath (Join-Path $Project 'public\storage')) { $notes += 'storage linked' } else { $warns += 'storage:link failed' }
    }
    $dbg = (Get-EnvValue 'APP_DEBUG').ToLower()
    $envName = (Get-EnvValue 'APP_ENV').ToLower()
    if (($dbg -eq 'true' -or $dbg -eq '1') -and ($LanMode -or $envName -eq 'production')) {
        $warns += 'APP_DEBUG=true: error pages show code'
        Write-LaunchLog 'WARNING: APP_DEBUG=true while LSPDMS is reachable from other devices / in production. Set APP_DEBUG=false in .env to hide error details.'
    }
    if ($script:FePlan -ne 'build') {
        $au = Get-EnvValue 'APP_URL'
        if ($au -and $au.TrimEnd('/') -ne $Url) { $warns += 'Vite allows only APP_URL'; Write-LaunchLog ('WARNING: Vite dev server CORS only allows APP_URL; open LSPDMS at that address (launcher URL: ' + $Url + ')') }
    }
    if (Get-OpcacheArgs) { $notes += 'OPcache ready' } elseif ($UseOpcache) { $warns += 'OPcache unavailable' }
    if ($warns.Count -gt 0) { return (Res 'warn' (($warns + $notes) -join ', ')) }
    if ($notes.Count -eq 0) { $notes += 'APP_KEY set' }
    Res 'ok' ($notes -join ', ')
}

# ---- PHP OPcache for the web server (php.ini is not changed). Probed once per launcher session ----
$script:OpcacheArgs = $null
function Get-OpcacheArgs {
    if (-not $UseOpcache) { return '' }
    if ($null -ne $script:OpcacheArgs) { return $script:OpcacheArgs }
    $script:OpcacheArgs = ''
    $set = @('-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0',
             '-d', 'opcache.memory_consumption=192', '-d', 'opcache.interned_strings_buffer=16', '-d', 'opcache.max_accelerated_files=20000',
             '-d', 'realpath_cache_size=4096K', '-d', 'realpath_cache_ttl=600')
    $mods = @()
    try { $mods = @(& php -m 2>$null | ForEach-Object { ([string]$_).Trim() }) } catch {}
    if ($mods -notcontains 'Zend OPcache') { $set = @('-d', 'zend_extension=opcache') + $set }   # php.ini does not load it: load it for this server only
    $ok = ''
    try { $ok = ([string](& php @set -r 'echo (int)(function_exists(''opcache_get_status'') && opcache_get_status() !== false);' 2>$null)).Trim() } catch {}
    if ($ok -eq '1') { $script:OpcacheArgs = ($set -join ' ') }
    $script:OpcacheArgs
}

# ---- Composer / npm: install only when the lock file changed (hashes in storage\app\launcher\deps.json) ----
# $true when the packages must be (re)installed: the marker is missing, the lock changed since the hash the launcher saved,
# or (nothing saved yet) the lock file is newer than the installed-packages marker
function Test-NeedInstall($lockFile, $marker, $savedHash) {
    if (-not (Test-Path -LiteralPath $marker)) { return $true }
    if (-not (Test-Path -LiteralPath $lockFile)) { return $false }
    if ($savedHash) { return ($savedHash -ne (Get-FileSha $lockFile)) }
    return ((Get-Item -LiteralPath $lockFile).LastWriteTimeUtc -gt (Get-Item -LiteralPath $marker).LastWriteTimeUtc.AddSeconds(2))
}
$script:NpmJob = $null
function Invoke-PackageCheck {
    $st = Read-State 'deps'
    $cHash = Get-StateValue $st 'composer'; $nHash = Get-StateValue $st 'npm'
    $cLock = Join-Path $Project 'composer.lock'; $nLock = Join-Path $Project 'package-lock.json'
    $needC = (-not (Test-Path -LiteralPath (Join-Path $Project 'vendor\autoload.php'))) -or (Test-NeedInstall $cLock (Join-Path $Project 'vendor\composer\installed.json') $cHash)
    $needN = (-not (Test-Path -LiteralPath (Join-Path $Project 'node_modules\vite\bin\vite.js'))) -or (Test-NeedInstall $nLock (Join-Path $Project 'node_modules\.package-lock.json') $nHash)
    $bits = @(); $state = 'ok'
    # npm runs in the background while Composer works (the front-end step waits for it)
    $script:NpmJob = $null
    if ($needN) {
        if (Get-Command npm -ErrorAction SilentlyContinue) {
            $script:NpmJob = Start-Hidden 'cmd.exe' '/c npm install --no-audit --no-fund' (Join-Path $Logs 'npm-install.log')
            $script:NpmStep = $script:CurStep
            $bits += 'npm installing'
        } else { $state = 'warn'; $bits += 'npm missing' }
    } elseif (-not $nHash -and (Test-Path -LiteralPath $nLock)) { $nHash = Get-FileSha $nLock }
    if ($needC) {
        $r = Get-LastRes (Install-Vendor $true)
        if ($r -and $r.State -eq 'ok') { $cHash = Get-FileSha $cLock; $bits = @('composer installed') + $bits }
        elseif (Test-Path -LiteralPath (Join-Path $Project 'vendor\autoload.php')) { $state = 'warn'; $bits = @('composer install failed, old packages kept (retried next launch)') + $bits }
        else { $why = 'install failed'; if ($r) { $why = $r.Detail }; return (Res 'fail' ('composer: ' + $why)) }
    } else {
        if (-not $cHash -and (Test-Path -LiteralPath $cLock)) { $cHash = Get-FileSha $cLock }
        $bits = @('composer up to date') + $bits
    }
    if (-not $needN) { $bits += 'npm up to date' }
    Save-State 'deps' ([pscustomobject]@{ composer = $cHash; npm = $nHash })
    Res $state ($bits -join ', ')
}
# waits for the npm install started above; saves its lock hash only when it worked (exit code 0), so a failed
# install is retried on the next launch
$script:NpmStep = $null
function Wait-NpmInstall {
    $p = $script:NpmJob
    if (-not $p) { return $true }
    Wait-Proc $p 1500
    $script:NpmJob = $null
    $vite = Test-Path -LiteralPath (Join-Path $Project 'node_modules\vite\bin\vite.js')
    if ($p.ExitCode -eq 0 -and $vite) {
        $st = Read-State 'deps'
        Save-State 'deps' ([pscustomobject]@{ composer = (Get-StateValue $st 'composer'); npm = (Get-FileSha (Join-Path $Project 'package-lock.json')) })
        return $true
    }
    $script:FailLog = Join-Path $Logs 'npm-install.log'
    # the package step already finished: mark it WARN there so the failed npm install is visible in the summary
    $note = 'npm install failed (exit ' + $p.ExitCode + ')'
    if ($vite) { $note += ', old packages kept - retried next launch' }
    if ($script:NpmStep) {
        if ($script:NpmStep.State -eq 'ok') { $script:NpmStep.State = 'warn' }
        $script:NpmStep.Detail = ([string]$script:NpmStep.Detail -replace 'npm installing', $note)
        Write-LaunchLog ('[WARN] ' + $script:NpmStep.Name + ' - ' + $note)
    }
    $false
}

# ---- front-end: Vite dev server or compiled assets (public\build) ----
# keep-dev  = a Vite dev server already answers on :VitePort and public\hot points at it (pages load the live sources)
# start-dev = start Vite now (this PC only: vite.config.js allows only APP_URL)
# build     = compiled assets; public\hot is removed (a hot file with no Vite behind it breaks every page)
function Get-FrontEndPlan {
    if ($LanMode) { return 'build' }   # other devices cannot load from a Vite dev server on this PC
    $viteUp = Test-VitePort
    # a hot file team mode moved aside comes back while its Vite dev server still runs, else it is dropped
    $moved = Join-Path $StateDir 'hot.moved'
    if (Test-Path -LiteralPath $moved) {
        $hot = Join-Path $Project 'public\hot'
        if ($viteUp -and -not (Test-Path -LiteralPath $hot)) { Move-Item -LiteralPath $moved -Destination $hot -Force -ErrorAction SilentlyContinue }
        else { Remove-Item -LiteralPath $moved -Force -ErrorAction SilentlyContinue }
    }
    if ($FrontEnd -eq 'dev') { if ($viteUp) { return 'keep-dev' } else { return 'start-dev' } }
    if ($FrontEnd -eq 'auto' -and $viteUp -and (Test-Path -LiteralPath (Join-Path $Project 'public\hot'))) { return 'keep-dev' }
    'build'
}
# what the compiled assets depend on besides files: the VITE_* settings (+ the values they copy) and a LAN address baked in
function Get-BuildSettingsHash($reverbHostOverride) {
    $envf = Join-Path $Project '.env'
    $lines = @()
    if (Test-Path -LiteralPath $envf) {
        $lines = @(Get-Content -LiteralPath $envf | Where-Object { $_ -match '^\s*(VITE_[A-Z0-9_]*|REVERB_APP_KEY|REVERB_HOST|REVERB_PORT|REVERB_SCHEME|APP_NAME|APP_URL)\s*=' } | ForEach-Object { $_.Trim() })
    }
    Get-TextSha (($lines -join "`n") + "`nhost-override=" + [string]$reverbHostOverride)
}
# why public\build must be rebuilt ('' = it is current)
function Get-BuildReason($root, $settingsHash, $savedHash) {
    $man = Join-Path $root 'public\build\manifest.json'
    if (-not (Test-Path -LiteralPath $man)) { return 'no compiled assets yet' }
    if (-not $savedHash) { return 'first check' }
    if ($savedHash -ne $settingsHash) { return 'settings changed' }
    $mt = (Get-Item -LiteralPath $man).LastWriteTimeUtc
    $files = @(Get-ChildItem -LiteralPath (Join-Path $root 'resources') -Recurse -File -ErrorAction SilentlyContinue)
    foreach ($n in 'vite.config.js', 'package.json', 'package-lock.json', 'postcss.config.js', 'tailwind.config.js') {
        $x = Join-Path $root $n
        if (Test-Path -LiteralPath $x) { $files += Get-Item -LiteralPath $x }
    }
    $newer = $files | Where-Object { $_.LastWriteTimeUtc -gt $mt } | Select-Object -First 1
    if ($newer) { return ('changed: ' + $newer.Name) }
    ''
}
function Get-ViteJs { Join-Path $Project 'node_modules\vite\bin\vite.js' }
function Invoke-FrontEnd {
    $plan = $script:FePlan
    $hot = Join-Path $Project 'public\hot'
    if (-not (Wait-NpmInstall)) {
        if ($plan -ne 'keep-dev' -and -not (Test-Path -LiteralPath (Join-Path $Project 'public\build\manifest.json'))) { return (Res 'fail' 'npm install failed - see npm-install.log') }
    }
    if ($plan -eq 'keep-dev') {
        return (Res 'ok' ('Vite dev server :' + $VitePort + ' (live reload)'))
    }
    if ($plan -eq 'start-dev') {
        $vj = Get-ViteJs
        if (-not (Test-Path -LiteralPath $vj)) { return (Res 'fail' 'Vite is not installed (npm install)') }
        $p = Start-Tracked 'vite' 'node' ('"' + $vj + '" --host 127.0.0.1 --port ' + $VitePort + ' --strictPort') (Join-Path $Logs 'vite.log')
        Add-BgJob 'vite' $script:CurStep 40 {
            param($j, $final)
            if ((Test-VitePort) -and (Test-Path -LiteralPath (Join-Path $Project 'public\hot'))) { return (Res 'ok' ('Vite dev server :' + $VitePort + ' (live reload)')) }
            if ($j.Data.Proc.HasExited -or $final) { $script:FailLog = Join-Path $Logs 'vite.log'; return (Res 'fail' 'Vite dev server did not start') }
            $null
        } @{ Proc = $p }
        return (Res 'bg' 'starting Vite dev server...')
    }
    # compiled assets: the hot file is removed only when nothing answers on the Vite port. A Vite dev server the
    # launcher did not start is never stopped: this PC keeps using it; team mode moves its hot file aside
    # (other devices cannot load from it) and the next this-PC launch puts it back while that Vite still runs
    $hotNote = ''
    if (Test-Path -LiteralPath $hot) {
        if (-not (Test-VitePort)) { Remove-Item -LiteralPath $hot -Force -ErrorAction SilentlyContinue }
        elseif ($LanMode) {
            try {
                New-Item -ItemType Directory -Force $StateDir | Out-Null
                Move-Item -LiteralPath $hot -Destination (Join-Path $StateDir 'hot.moved') -Force -ErrorAction Stop
                $hotNote = 'Vite on :' + $VitePort + ' left running; its public\hot moved aside for team mode'
            } catch { $hotNote = 'public\hot could not be moved - other devices may get no styles' }
        }
        else { $hotNote = 'public\hot kept: a Vite dev server you started runs on :' + $VitePort }
    }
    $override = ''
    if ($LanIp) { $override = $LanIp }
    $sh = Get-BuildSettingsHash $override
    $why = Get-BuildReason $Project $sh (Get-StateValue (Read-State 'build') 'settings')
    if (-not $why) {
        if ($hotNote) { return (Res 'warn' $hotNote) }
        return (Res 'ok' 'compiled assets up to date')
    }
    $log = Join-Path $Logs 'build.log'
    Remove-Item -LiteralPath $log, "$log.err" -Force -ErrorAction SilentlyContinue
    if ($override) { $env:VITE_REVERB_HOST = $override }   # LAN: browsers on other devices must reach live chat on this PC
    try {
        $vj = Get-ViteJs
        if ((Test-Path -LiteralPath $vj) -and (Get-Command node -ErrorAction SilentlyContinue)) { $p = Start-Hidden 'node' ('"' + $vj + '" build') $log }
        else { $p = Start-Hidden 'cmd.exe' '/c npm run build' $log }
    } finally { Remove-Item Env:\VITE_REVERB_HOST -ErrorAction SilentlyContinue }
    Add-BgJob 'build' $script:CurStep 600 {
        param($j, $final)
        $p = $j.Data.Proc
        if (-not $p.HasExited -and -not $final) { return $null }
        if (-not $p.HasExited) { try { taskkill /F /T /PID $p.Id *> $null } catch {} }
        $txt = ''
        try { $txt = [string](Get-Content -LiteralPath $j.Data.Log -Raw) } catch {}
        if ($p.HasExited -and ($p.ExitCode -eq 0 -or $txt -match 'built in')) {
            Save-State 'build' ([pscustomobject]@{ settings = $j.Data.Hash; at = (Get-Date).ToString('s') })
            $m = [regex]::Match($txt, 'built in ([0-9\.]+m?s)')
            if ($m.Success) { return (Res 'ok' ('rebuilt in ' + $m.Groups[1].Value)) }
            return (Res 'ok' 'assets rebuilt')
        }
        $script:FailLog = $j.Data.Log
        if (Test-Path -LiteralPath (Join-Path $Project 'public\build\manifest.json')) { return (Res 'warn' 'build failed - previous assets kept') }
        Res 'fail' 'npm run build failed - see build.log'
    } @{ Proc = $p; Log = $log; Hash = $sh }
    Res 'bg' ('building (' + $why + ')...')
}

# ---- framework caches ----
function Invoke-Caches {
    $p = Start-Hidden 'php' 'artisan optimize:clear --no-interaction' (Join-Path $Logs 'cache.log')
    Wait-Proc $p 120
    if ($null -ne $p.ExitCode -and $p.ExitCode -ne 0) { $script:FailLog = Join-Path $Logs 'cache.log'; return (Res 'warn' ('optimize:clear exit code ' + $p.ExitCode)) }
    if ($script:FePlan -ne 'build' -or -not $WarmCaches) { return (Res 'ok' 'caches flushed') }
    # compiled-assets mode: pre-compile views + events in the background.
    # Config and routes are NOT cached (staging relies on DB_DATABASE env overrides; routes may hold closures)
    $w = Start-Hidden 'cmd.exe' '/c php artisan view:cache --no-interaction && php artisan event:cache --no-interaction' (Join-Path $Logs 'warm.log')
    Add-BgJob 'warm' $script:CurStep 180 {
        param($j, $final)
        $p = $j.Data.Proc
        if (-not $p.HasExited -and -not $final) { return $null }
        if ($p.HasExited -and ($null -eq $p.ExitCode -or $p.ExitCode -eq 0)) { return (Res 'ok' 'flushed; views + events pre-compiled') }
        if (-not $p.HasExited) { try { taskkill /F /T /PID $p.Id *> $null } catch {} }
        Res 'warn' 'flushed; pre-compile failed (warm.log)'
    } @{ Proc = $w }
    Res 'bg' 'flushed, pre-compiling views...'
}

# ---- database: pending migrations + mysqldump backup ----
function Get-PendingMigrations {
    $log = Join-Path $Logs 'migrate-status.log'
    Remove-Item -LiteralPath $log, "$log.err" -Force -ErrorAction SilentlyContinue
    $p = Start-Hidden 'php' 'artisan migrate:status --pending --no-ansi --no-interaction' $log
    Wait-Proc $p 120
    $txt = (@(Get-LogTail $log 300)) -join "`n"
    if ($txt -match 'Migration table not found') { return @{ State = 'fresh'; Count = 0 } }
    if ($null -ne $p.ExitCode -and $p.ExitCode -ne 0) { return @{ State = 'unknown'; Count = 0 } }
    if ($txt -match 'No pending migrations') { return @{ State = 'none'; Count = 0 } }
    $n = [regex]::Matches($txt, '(?m)^\s*\S+\s*\.*\s*Pending\s*$').Count
    if ($n -gt 0) { return @{ State = 'pending'; Count = $n } }
    @{ State = 'unknown'; Count = 0 }
}
# the dump tool of the running database server first (same version), then Laragon's, then PATH
function Find-MysqlDump {
    $dirs = @()
    foreach ($id in (Get-PortOwners $MySQLPort)) {
        $pr = Get-Process -Id $id -ErrorAction SilentlyContinue
        if ($pr -and $pr.Path) { $dirs += (Split-Path -Parent $pr.Path) }
    }
    $m = Find-MySqld
    if ($m) { $dirs += (Split-Path -Parent $m.Exe) }
    foreach ($d in $dirs) {
        foreach ($n in 'mariadb-dump.exe', 'mysqldump.exe') { $x = Join-Path $d $n; if (Test-Path -LiteralPath $x) { return $x } }
    }
    $c = Get-Command mysqldump -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $null
}
$script:LastBackup = $null
function Backup-Database($label = 'pre-migrate') {
    $name = Get-EnvValue 'DB_DATABASE'
    if (-not $name) { return (Res 'fail' 'DB_DATABASE is empty in .env') }
    $exe = Find-MysqlDump
    if (-not $exe) { return (Res 'fail' 'mysqldump not found') }
    $dbh = Get-EnvValue 'DB_HOST'; if (-not $dbh) { $dbh = '127.0.0.1' }
    $dbp = Get-EnvValue 'DB_PORT'; if (-not $dbp) { $dbp = '3306' }
    $dbu = Get-EnvValue 'DB_USERNAME'; if (-not $dbu) { $dbu = 'root' }
    $dbw = Get-EnvValue 'DB_PASSWORD'
    $dir = Join-Path $Project 'storage\app\manual-backups'
    try { New-Item -ItemType Directory -Force $dir | Out-Null } catch {}
    $safe = $name -replace '[^A-Za-z0-9_\-]', '_'
    $file = Join-Path $dir ($label + '-' + $safe + '-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
    $log = Join-Path $Logs 'backup.log'
    $base = '--host=' + $dbh + ' --port=' + $dbp + ' --user="' + $dbu + '" --single-transaction --quick --routines --triggers --hex-blob --no-tablespaces --default-character-set=utf8mb4 --result-file="' + $file + '"'
    $ok = $false
    foreach ($extra in @('', ' --column-statistics=0')) {
        Remove-Item -LiteralPath $log, "$log.err", $file -Force -ErrorAction SilentlyContinue
        # the password reaches mysqldump through MYSQL_PWD of this one child process - never on its command line
        if ($dbw) { $env:MYSQL_PWD = $dbw }
        try { $p = Start-Hidden $exe ($base + $extra + ' "' + $name + '"') $log }
        finally { Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue }
        Wait-Proc $p 1800
        $tail = ''
        try { $tail = (@(Get-Content -LiteralPath $file -Tail 3 -ErrorAction Stop)) -join ' ' } catch {}
        if ((Test-Path -LiteralPath $file) -and ($null -eq $p.ExitCode -or $p.ExitCode -eq 0) -and $tail -match 'Dump completed') { $ok = $true; break }
        $err = (@(Get-LogTail $log 20)) -join ' '
        if ($err -notmatch '(?i)column.statistics') { break }   # a MySQL 8 tool against MariaDB: retry once without column statistics
    }
    if (-not $ok) {
        Remove-Item -LiteralPath $file -Force -ErrorAction SilentlyContinue
        $script:FailLog = $log
        return (Res 'fail' 'mysqldump failed - see backup.log')
    }
    $script:LastBackup = $file
    # keep only the newest $KeepBackups launcher backups (backups made any other way are never touched)
    @(Get-ChildItem -LiteralPath $dir -Filter ($label + '-*.sql') -File -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -Skip ([math]::Max(1, [int]$KeepBackups))) |
        ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force -ErrorAction SilentlyContinue }
    $mb = [math]::Round((Get-Item -LiteralPath $file).Length / 1MB, 1)
    Res 'ok' ([IO.Path]::GetFileName($file) + ' (' + $mb + ' MB)')
}

# ---- database security: MariaDB answers this PC only, its inbound firewall rules are off, LSPDMS has its own DB account ----
# Runs on every launch before migrate. Each part first checks (about 0.3 s together) and changes nothing that is already done.
# Passwords only travel in environment variables of one child process; they are never shown or logged.
$script:DbSecNote = ''
function Test-LocalDbHost($h) {
    $h = ([string]$h).Trim().ToLower()
    (-not $h) -or $h -eq '127.0.0.1' -or $h -eq 'localhost' -or $h -eq '::1'
}
# the account name: lspdms_app for the usual database, one per database otherwise (two installs never share one)
function Get-DbAppUser($db) {
    if (-not $db -or $db -eq 'lspdms') { return 'lspdms_app' }
    $u = 'lspdms_app_' + ($db -replace '[^A-Za-z0-9_]', '_')
    if ($u.Length -gt 80) { $u = $u.Substring(0, 80) }
    $u
}
# 32 letters / digits from the crypto RNG (about 190 bits): safe unquoted in .env and on any command line
function New-DbPassword($len = 32) {
    $set = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $buf = New-Object byte[] 64
    $sb = New-Object Text.StringBuilder
    try {
        while ($sb.Length -lt $len) {
            $rng.GetBytes($buf)
            foreach ($b in $buf) { if ($b -lt 248 -and $sb.Length -lt $len) { [void]$sb.Append($set[$b % 62]) } }   # 248 = 4 x 62: no bias
        }
    } finally { $rng.Dispose() }
    $sb.ToString()
}
# .env is written to a temporary file first and swapped in with File.Replace, so it is never left half-written
function Write-EnvAtomic($envf, $txt) {
    $tmp = $envf + '.lspdms-tmp'
    for ($try = 1; $try -le 3; $try++) {
        try {
            [IO.File]::WriteAllText($tmp, $txt, (New-Object Text.UTF8Encoding($false)))
            if (Test-Path -LiteralPath $envf) { [IO.File]::Replace($tmp, $envf, [NullString]::Value) } else { [IO.File]::Move($tmp, $envf) }
            return $true
        } catch { Start-Sleep -Milliseconds 150 }
    }
    Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    $false
}
# a copy of .env outside the project (per Windows user), so deleting / reinstalling the project folder keeps
# APP_KEY (saved passwords stay readable) and the DB login. One copy per project folder.
function Get-EnvKeepFile {
    $id = (Get-TextSha ([IO.Path]::GetFullPath($Project).TrimEnd('\').ToLowerInvariant())).Substring(0, 16)
    Join-Path $env:LOCALAPPDATA ('LSPDMS\env-keep\' + $id + '.env')
}
function Save-EnvKeep {
    $envf = Join-Path $Project '.env'
    try { $txt = [IO.File]::ReadAllText($envf) } catch { return }
    if ($txt -notmatch '(?m)^APP_KEY=\S+') { return }
    $keep = Get-EnvKeepFile
    try {
        if ((Test-Path -LiteralPath $keep) -and ([IO.File]::ReadAllText($keep) -ceq $txt)) { return }
        New-Item -ItemType Directory -Force (Split-Path $keep) | Out-Null
        [void](Write-EnvAtomic $keep $txt)
    } catch {}
}
# .env is missing (fresh download into a folder that had LSPDMS before): bring back the kept copy. Returns $true when restored
function Restore-EnvKeep {
    $envf = Join-Path $Project '.env'
    $keep = Get-EnvKeepFile
    if ((Test-Path -LiteralPath $envf) -or -not (Test-Path -LiteralPath $keep)) { return $false }
    try { $txt = [IO.File]::ReadAllText($keep) } catch { return $false }
    if ($txt -notmatch '(?m)^APP_KEY=\S+') { return $false }
    if (-not (Write-EnvAtomic $envf $txt)) { return $false }
    Write-LaunchLog '.env restored from the copy kept by the previous install (same APP_KEY and DB login)'
    $true
}
function Save-EnvLogin($user, $pw) {
    $envf = Join-Path $Project '.env'
    if (-not (Test-Path -LiteralPath $envf)) { return $false }
    try { $txt = [IO.File]::ReadAllText($envf) } catch { return $false }
    $nl = "`n"; if ($txt -match "`r`n") { $nl = "`r`n" }
    $txt = Set-EnvLine $txt 'DB_USERNAME' $user $nl
    $txt = Set-EnvLine $txt 'DB_PASSWORD' $pw $nl
    $ok = Write-EnvAtomic $envf $txt
    if ($ok) { Save-EnvKeep }
    $ok
}
# storage\logs\launcher\dbsec.log: what went wrong and how to fix it (shown by "Open log")
function Write-DbSecLog($lines) {
    $f = Join-Path $Logs 'dbsec.log'
    try { Set-Content -LiteralPath $f -Value (@((Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) + @($lines)) -Encoding UTF8 } catch {}
    $script:FailLog = $f
    Write-LaunchLog ('database security: ' + (@($lines)[0]))
}
# one PHP/PDO run (the same driver Laravel uses). mode check = log in with a login and report who / bind-address / database access;
# mode provision = log in as root and (re)create the app account. Returns the parsed JSON answer ({ ok, ... })
function Invoke-DbPhp($vars) {
    $code = @'
<?php
$h = (string)getenv("LSP_H"); $p = (int)getenv("LSP_P"); $d = (string)getenv("LSP_D"); $mode = (string)getenv("LSP_MODE");
$opt = [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$dsn = "mysql:host=$h;port=$p";
$fail = function (Throwable $e, string $stage) {
  $m = $e->getMessage();
  foreach (["LSP_W", "LSP_AW"] as $k) { $s = (string)getenv($k); if ($s !== "") { $m = str_replace($s, "***", $m); } }
  $c = preg_match('/\[(\d{4})\]/', $m, $x) ? (int)$x[1] : (int)$e->getCode();
  echo json_encode(["ok" => false, "stage" => $stage, "code" => $c, "err" => $m]); exit(1);
};
if ($mode === "check") {
  try { $pdo = new PDO($dsn, (string)getenv("LSP_U"), (string)getenv("LSP_W"), $opt); } catch (Throwable $e) { $fail($e, "login"); }
  try {
    $r = $pdo->query("SELECT CURRENT_USER(), @@bind_address")->fetch(PDO::FETCH_NUM);
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?"); $s->execute([$d]);
    $db = (int)$s->fetchColumn() > 0; $use = false;
    if ($db) { try { $pdo->exec("USE `" . str_replace("`", "``", $d) . "`"); $use = true; } catch (Throwable $e) {} }
    echo json_encode(["ok" => true, "user" => (string)$r[0], "bind" => $r[1], "db" => $db, "use" => $use]); exit(0);
  } catch (Throwable $e) { $fail($e, "check"); }
}
if ($mode === "provision") {
  $app = (string)getenv("LSP_AU"); $aw = (string)getenv("LSP_AW");
  if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $d)) { $fail(new RuntimeException("DB_DATABASE may only use letters, digits, _ and $ for the launcher's own account"), "input"); }
  if (!preg_match('/^[A-Za-z0-9_]{1,80}$/', $app) || !preg_match('/^[A-Za-z0-9]{24,}$/', $aw)) { $fail(new RuntimeException("invalid account name or password"), "input"); }
  $roots = json_decode((string)base64_decode((string)getenv("LSP_ROOTS")), true);
  $pdo = null; $last = new RuntimeException("no root login to try");
  foreach (is_array($roots) ? $roots : [] as $c) {
    try { $pdo = new PDO($dsn, (string)($c["u"] ?? ""), (string)($c["w"] ?? ""), $opt); break; } catch (Throwable $e) { $last = $e; }
  }
  if (!$pdo) { $fail($last, "root"); }
  try {
    $who = (string)$pdo->query("SELECT CURRENT_USER()")->fetchColumn();
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$d` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    // in GRANT, _ and % in a database name are wildcards: escaped so only this database is meant
    $g = "`" . str_replace(["_", "%"], ["\\_", "\\%"], $d) . "`";
    // what LSPDMS does on its own database: app reads/writes, migrations (tables, indexes, foreign keys), the backup import
    // (DELETE + INSERT), and mysqldump --single-transaction --routines --triggers (LOCK TABLES, SHOW VIEW, TRIGGER, EVENT, routines)
    $priv = "SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EVENT, CREATE ROUTINE, ALTER ROUTINE, EXECUTE";
    foreach (["localhost", "127.0.0.1", "::1"] as $host) {
      $a = $pdo->quote($app) . "@" . $pdo->quote($host);
      $pdo->exec("CREATE USER IF NOT EXISTS $a IDENTIFIED BY " . $pdo->quote($aw));
      $pdo->exec("ALTER USER $a IDENTIFIED BY " . $pdo->quote($aw));
      $pdo->exec("REVOKE ALL PRIVILEGES, GRANT OPTION FROM $a");
      $pdo->exec("GRANT $priv ON $g.* TO $a");
    }
    echo json_encode(["ok" => true, "root" => $who]); exit(0);
  } catch (Throwable $e) { $fail($e, "grant"); }
}
echo json_encode(["ok" => false, "stage" => "input", "code" => 0, "err" => "unknown mode"]); exit(1);
'@
    $php = Join-Path $Logs 'dbsec.php'
    $log = Join-Path $Logs 'dbsec-run.log'
    $out = ''
    try {
        foreach ($k in $vars.Keys) { [Environment]::SetEnvironmentVariable([string]$k, [string]$vars[$k], 'Process') }
        Set-Content -LiteralPath $php -Value $code -Encoding ASCII
        Remove-Item -LiteralPath $log, "$log.err" -Force -ErrorAction SilentlyContinue
        $p = Start-Hidden 'php' ('"' + $php + '"') $log
        Wait-Proc $p 45
        $out = ([string](Get-Content -LiteralPath $log -Raw)).Trim()
        if (-not $out) { $out = ([string](Get-Content -LiteralPath "$log.err" -Raw)).Trim() }
    } catch { $out = $_.Exception.Message }
    finally {
        foreach ($k in $vars.Keys) { [Environment]::SetEnvironmentVariable([string]$k, $null, 'Process') }
        Remove-Item -LiteralPath $php, $log, "$log.err" -Force -ErrorAction SilentlyContinue
    }
    try { $o = $out | ConvertFrom-Json -ErrorAction Stop; if ($o -and $o.PSObject.Properties['ok']) { return $o } } catch {}
    if (-not $out) { $out = 'no answer from PHP' }
    [pscustomobject]@{ ok = $false; stage = 'php'; code = 0; err = (Fit (($out -replace '\s+', ' ').Trim()) 200) }
}
function Get-DbLoginCheck($h, $p, $u, $w) {
    Invoke-DbPhp @{ LSP_MODE = 'check'; LSP_H = $h; LSP_P = $p; LSP_U = $u; LSP_W = $w; LSP_D = (Get-EnvValue 'DB_DATABASE') }
}
# (re)creates LSPDMS's own account through root, saves its new password in .env and proves the new login works.
# root itself is never written to .env. Returns @{ State = 'ok' | 'fail'; Detail; User; Check }
function Ensure-AppDbAccount($h, $p) {
    $db = Get-EnvValue 'DB_DATABASE'
    if (-not $db) { return @{ State = 'fail'; Detail = 'DB_DATABASE is empty in .env' } }
    $user = Get-DbAppUser $db
    # root logins to try: the one in .env (when .env still uses root), then the usual local defaults
    $roots = @()
    $eu = Get-EnvValue 'DB_USERNAME'
    if (-not $eu -or $eu -eq 'root') { $roots += [pscustomobject]@{ u = 'root'; w = (Get-EnvValue 'DB_PASSWORD') } }
    foreach ($dw in @('', 'root')) { if (@($roots | Where-Object { $_.w -eq $dw }).Count -eq 0) { $roots += [pscustomobject]@{ u = 'root'; w = $dw } } }
    $pw = New-DbPassword
    $rj = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes((ConvertTo-Json -InputObject @($roots) -Compress)))
    $r = Invoke-DbPhp @{ LSP_MODE = 'provision'; LSP_H = $h; LSP_P = $p; LSP_D = $db; LSP_AU = $user; LSP_AW = $pw; LSP_ROOTS = $rj }
    if (-not $r.ok) {
        if ([string]$r.stage -eq 'root') {
            Write-DbSecLog @('The database login in .env does not work, and root could not log in to make LSPDMS''s own account either.',
                ('MySQL said: ' + [string]$r.err),
                'Fix: put the MariaDB root login in .env once (DB_USERNAME=root and DB_PASSWORD=<the root password>), then start the launcher again.',
                ('The launcher then creates the account ' + $user + ', saves its new password in .env and stops using root.'))
            return @{ State = 'fail'; Detail = 'DB login refused, root login too' }
        }
        Write-DbSecLog @(('Creating the database account ' + $user + ' failed (' + [string]$r.stage + '): ' + [string]$r.err), '.env was not changed.')
        return @{ State = 'fail'; Detail = ('could not create DB account ' + $user) }
    }
    if (-not (Save-EnvLogin $user $pw)) {
        Write-DbSecLog @(('The account ' + $user + ' was made, but .env could not be written (is it read-only or open in another program?).'), 'Close the program that holds .env, then start the launcher again.')
        return @{ State = 'fail'; Detail = 'could not save the DB login in .env' }
    }
    $chk = Get-DbLoginCheck $h $p $user $pw
    if (-not $chk.ok -or -not $chk.use) {
        Write-DbSecLog @(('The new account ' + $user + ' was saved in .env but cannot open the database: ' + [string]$chk.err))
        return @{ State = 'fail'; Detail = ('new DB account ' + $user + ' cannot log in') }
    }
    Write-LaunchLog ('database: own account ' + $user + ' (re)created by ' + [string]$r.root + ' with rights on ' + $db + ' only; its password is in .env (never shown). .env no longer uses root')
    @{ State = 'ok'; Detail = 'own account'; User = $user; Check = $chk }
}
# true when $port is in a firewall port list such as "80,3306" or "3000-4000" ("*" / Any never counts)
function Test-PortListed($ports, $port) {
    foreach ($t in ([string]$ports).Split(',')) {
        $t = $t.Trim()
        if ($t -match '^\d+$' -and [int]$t -eq [int]$port) { return $true }
        if ($t -match '^(\d+)-(\d+)$' -and [int]$Matches[1] -le [int]$port -and [int]$Matches[2] -ge [int]$port) { return $true }
    }
    $false
}
# enabled inbound Allow rules that let other devices reach the database (mysqld.exe / mariadbd.exe, or TCP to its port),
# plus node.exe ones (only reported). Read through the firewall COM API: about 0.1 s instead of several seconds
function Get-DbFirewallRules($port) {
    $r = @{ Db = @(); Node = @(); Error = '' }
    try {
        $fw = New-Object -ComObject HNetCfg.FwPolicy2
        foreach ($x in @($fw.Rules)) {
            if ($x.Direction -ne 1 -or $x.Action -ne 1 -or -not $x.Enabled) { continue }   # 1 = inbound, 1 = allow
            $app = [string]$x.ApplicationName
            $leaf = ''; try { if ($app) { $leaf = [IO.Path]::GetFileName($app).ToLower() } } catch {}
            $o = [pscustomobject]@{ Name = [string]$x.Name; Program = $app; Ports = [string]$x.LocalPorts }
            if ($leaf -match '^(mysqld|mariadbd)(-nt)?\.exe$' -or (($x.Protocol -eq 6 -or $x.Protocol -eq 256) -and (Test-PortListed $x.LocalPorts $port))) { $r.Db += $o }
            elseif ($leaf -eq 'node.exe') { $r.Node += $o }
        }
    } catch { $r.Error = $_.Exception.Message }
    $r
}
# switches those database rules off (Disable-NetFirewallRule: reversible, nothing is deleted). Rules with the same name that
# are not database Allow rules are never touched. Returns @{ Done; Errors } (descriptions of the rules)
function Disable-DbFirewallRules($names, $port) {
    $done = @(); $errs = @()
    foreach ($rule in @(Get-NetFirewallRule -DisplayName @($names) -ErrorAction SilentlyContinue)) {
        Pump   # these firewall cmdlets are slow (a few seconds in all, first launch only): keep the screen moving
        if ([string]$rule.Direction -ne 'Inbound' -or [string]$rule.Action -ne 'Allow' -or [string]$rule.Enabled -ne 'True') { continue }
        $prog = [string](($rule | Get-NetFirewallApplicationFilter -ErrorAction SilentlyContinue).Program)
        $pf = $rule | Get-NetFirewallPortFilter -ErrorAction SilentlyContinue
        $lp = (@($pf.LocalPort) | ForEach-Object { [string]$_ }) -join ','
        $leaf = ''; try { if ($prog -and $prog -ne 'Any') { $leaf = [IO.Path]::GetFileName($prog).ToLower() } } catch {}
        $tcp = ([string]$pf.Protocol -eq 'TCP' -or [string]$pf.Protocol -eq 'Any')
        if (-not ($leaf -match '^(mysqld|mariadbd)(-nt)?\.exe$') -and -not ($tcp -and (Test-PortListed $lp $port))) { continue }
        $what = '"' + $rule.DisplayName + '" (' + $prog + ', ' + [string]$pf.Protocol + ' ' + $lp + ', ' + [string]$rule.Profile + ')'
        try {
            Disable-NetFirewallRule -Name $rule.Name -ErrorAction Stop
            $done += $what
            Write-LaunchLog ('firewall: disabled inbound Allow rule ' + $what + ' - not deleted; it can be switched on again in Windows Defender Firewall')
        } catch {
            $errs += $what
            Write-LaunchLog ('firewall: could not disable ' + $what + ': ' + $_.Exception.Message)
        }
    }
    @{ Done = $done; Errors = $errs }
}
function Protect-DbFirewall($port) {
    $f = Get-DbFirewallRules $port
    if ($f.Error) { Write-LaunchLog ('firewall could not be read: ' + $f.Error); return (Res 'warn' 'firewall could not be read') }
    # node.exe Allow rules are only reported (once per change): other programs may need them
    $sig = (@($f.Node | ForEach-Object { $_.Program } | Sort-Object -Unique)) -join ';'
    $st = Read-State 'dbsec'
    if ($sig -ne (Get-StateValue $st 'node')) {
        if ($sig) { Write-LaunchLog ('firewall note (not changed): node.exe may accept connections from other devices through ' + @($f.Node).Count + ' inbound Allow rule(s), e.g. "' + $f.Node[0].Name + '" (' + $f.Node[0].Program + '). The launcher keeps its Vite dev server on this PC; remove the rule in Windows Defender Firewall if no other program needs it.') }
        Save-State 'dbsec' ([pscustomobject]@{ node = $sig })
    }
    if (@($f.Db).Count -eq 0) { return (Res 'ok' 'no inbound database rule') }
    if (-not (Test-Admin)) { return (Res 'warn' 'firewall: run as Administrator') }
    $d = Disable-DbFirewallRules @($f.Db | ForEach-Object { $_.Name } | Select-Object -Unique) $port
    if (@($d.Errors).Count -gt 0) { return (Res 'warn' ('firewall: ' + @($d.Errors).Count + ' rule(s) still open')) }
    Res 'ok' (([string]@($d.Done).Count) + ' inbound rule(s) disabled')
}
# makes the [mysqld] bind-address 127.0.0.1 in this my.ini: an active bind-address line in a server section is corrected,
# otherwise one line is added under [mysqld] (after its port line). A dated backup copy is kept next to it.
# Read and written byte for byte (Latin-1), so every other line stays exactly as it was. Returns @{ Changed; Backup; Error }
function Set-IniBindAddress($cfg) {
    $r = @{ Changed = $false; Backup = ''; Error = '' }
    $enc = [Text.Encoding]::GetEncoding(28591)
    try { $txt = $enc.GetString([IO.File]::ReadAllBytes($cfg)) } catch { $r.Error = 'cannot read ' + $cfg; return $r }   # (ReadAllText would drop a BOM)
    $nl = "`n"; if ($txt -match "`r`n") { $nl = "`r`n" }
    $lines = New-Object Collections.Generic.List[string]
    foreach ($l in ($txt -split "\r?\n")) { $lines.Add($l) }
    $sec = ''; $found = $false; $edit = $false; $mysqld = -1; $portAt = -1
    for ($i = 0; $i -lt $lines.Count; $i++) {
        $hm = [regex]::Match($lines[$i], '^(?:ï»¿)?\s*\[([^\]]+)\]')
        if ($hm.Success) { $sec = $hm.Groups[1].Value.Trim().ToLower(); if ($sec -eq 'mysqld' -and $mysqld -lt 0) { $mysqld = $i }; continue }
        if ($sec -notmatch '^(mysqld|mariadb|mariadbd|server)(-[0-9.]+)?$') { continue }
        if ($sec -eq 'mysqld' -and $portAt -lt 0 -and $mysqld -ge 0 -and $lines[$i] -match '^\s*port\s*=') { $portAt = $i }
        $bm = [regex]::Match($lines[$i], '^(\s*)bind[-_]address\s*=\s*(.*)$', 'IgnoreCase')
        if (-not $bm.Success) { continue }
        $found = $true
        $v = ($bm.Groups[2].Value -replace '\s+#.*$', '').Trim().Trim('"', "'")
        if ($v -ne '127.0.0.1') { $lines[$i] = $bm.Groups[1].Value + 'bind-address=127.0.0.1'; $edit = $true }
    }
    if (-not $found) {
        if ($portAt -ge 0) { $lines.Insert($portAt + 1, 'bind-address=127.0.0.1') }
        elseif ($mysqld -ge 0) { $lines.Insert($mysqld + 1, 'bind-address=127.0.0.1') }
        else {
            $at = $lines.Count; if ($at -gt 0 -and $lines[$at - 1] -eq '') { $at-- }
            $lines.Insert($at, '[mysqld]'); $lines.Insert($at + 1, 'bind-address=127.0.0.1')
        }
        $edit = $true
    }
    if (-not $edit) { return $r }
    $bak = $cfg + '.lspdms-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak'
    $tmp = $cfg + '.lspdms-tmp'
    try {
        Copy-Item -LiteralPath $cfg -Destination $bak -Force -ErrorAction Stop
        [IO.File]::WriteAllText($tmp, ($lines -join $nl), $enc)
        [IO.File]::Replace($tmp, $cfg, [NullString]::Value)
    } catch {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
        $r.Error = 'cannot write ' + $cfg + ': ' + $_.Exception.Message
        return $r
    }
    $r.Changed = $true; $r.Backup = $bak
    $r
}
# restarts the database so it reads my.ini again: the Windows service (Restart-Service in a background job, so the
# screen keeps moving), or the mysqld the launcher itself started. Returns 'OK' or 'ERR: why'
function Restart-DbService($name) {
    $j = Start-Job -ScriptBlock { param($n) try { Restart-Service -Name $n -Force -ErrorAction Stop; 'OK' } catch { 'ERR: ' + $_.Exception.Message } } -ArgumentList $name
    $t = Get-Date
    while ($j.State -eq 'Running' -and ((Get-Date) - $t).TotalSeconds -lt 180) { Pump }
    $o = 'ERR: the service did not restart within 3 minutes'
    if ($j.State -ne 'Running') { $o = [string](@(Receive-Job $j -ErrorAction SilentlyContinue)[-1]) } else { Stop-Job $j }
    Remove-Job $j -Force -ErrorAction SilentlyContinue
    $o
}
function Restart-DbServer($m) {
    if ($m.Service) { return (Restart-DbService $m.Service) }
    Stop-TrackedName 'mysql'
    $t = Get-Date
    while ((Test-Port $MySQLPort) -and ((Get-Date) - $t).TotalSeconds -lt 30) { Pump }
    # its rows are not this step's: the background start reports to nobody, this step checks the port itself
    $cs = $script:CurStep; $script:CurStep = $null
    try { [void](Start-Database); Wait-BgJobs @('db') } finally { $script:CurStep = $cs }
    if (Test-Port $MySQLPort) { return 'OK' }
    'ERR: MySQL did not start again - see mysql.log'
}
# (a) the database must listen on 127.0.0.1 only. $chk = a login check that already ran ($u / $w = the .env login)
function Ensure-DbLocalOnly($h, $p, $chk, $u, $w) {
    if ([string]$chk.bind -eq '127.0.0.1') { return (Res 'ok' 'localhost only') }
    $was = [string]$chk.bind; if (-not $was) { $was = 'every address' }
    $m = Find-MySqld
    if (-not $m) { Write-DbSecLog @('The database listens on ' + $was + ', but its program (mysqld.exe) was not found, so bind-address was not changed.'); return (Res 'warn' 'NOT localhost-only (no mysqld)') }
    # only the my.ini of the program that really serves the port is edited
    foreach ($id in (Get-PortOwners $p)) {
        $pr = Get-Process -Id $id -ErrorAction SilentlyContinue
        $pp = ''; try { $pp = [IO.Path]::GetFullPath([string]$pr.Path) } catch {}
        if ($pp -and $m.Exe -and $pp -ne [IO.Path]::GetFullPath([string]$m.Exe)) {
            Write-DbSecLog @(('Port ' + $p + ' is served by ' + $pp + ', not by ' + $m.Exe + ', so bind-address was not changed.'), 'Set bind-address=127.0.0.1 under [mysqld] in that server''s my.ini and restart it.')
            return (Res 'warn' 'NOT localhost-only (other MySQL)')
        }
    }
    if (-not $m.Service -and -not (Get-TrackedProc 'mysql')) {
        Write-DbSecLog @('The database was started by another program (not the launcher, not a Windows service), so it was not restarted.', 'Stop it and start LSPDMS again; the launcher then starts it on 127.0.0.1 only.')
        return (Res 'warn' 'NOT localhost-only (restart it)')
    }
    $ini = $null
    if ($m.Cfg -and (Test-Path -LiteralPath $m.Cfg)) {
        $ini = Set-IniBindAddress $m.Cfg
        if ($ini.Error) { Write-DbSecLog @('bind-address could not be set: ' + $ini.Error); return (Res 'warn' 'NOT localhost-only (my.ini)') }
        if ($ini.Changed) { Write-LaunchLog ('database: bind-address=127.0.0.1 written to ' + $m.Cfg + ' (old file kept as ' + $ini.Backup + ')') }
    } elseif ($m.Service) {
        Write-DbSecLog @('The database service ' + $m.Service + ' has no --defaults-file, so its my.ini is unknown and bind-address was not changed.')
        return (Res 'warn' 'NOT localhost-only (no my.ini)')
    }
    $rs = Restart-DbServer $m
    $up = ($rs -eq 'OK') -and (Wait-Port $p 60)
    Write-LaunchLog ('database: restarted to apply bind-address (' + $rs + ', port ' + $p + ' up: ' + $up + ')')
    if (-not $up) {
        if ($ini -and $ini.Changed) {
            Copy-Item -LiteralPath $ini.Backup -Destination $m.Cfg -Force -ErrorAction SilentlyContinue
            [void](Restart-DbServer $m); [void](Wait-Port $p 60)
            Write-DbSecLog @(('The database did not start again after bind-address=127.0.0.1 was added (' + $rs + '). The old my.ini was put back and the database restarted.'), ('Check the database error log; the edited file is not kept, the original is ' + $m.Cfg + '.'))
            return (Res 'warn' 'restart failed - my.ini restored')
        }
        Write-DbSecLog @('The database did not start again: ' + $rs)
        return (Res 'warn' 'database restart failed')
    }
    $c2 = Get-DbLoginCheck $h $p $u $w
    if ($c2.ok -and [string]$c2.bind -eq '127.0.0.1') {
        Write-LaunchLog ('database: now listens on 127.0.0.1 only (was ' + $was + ')')
        return (Res 'ok' 'localhost only')
    }
    $now = [string]$c2.bind; if (-not $c2.ok) { $now = 'unknown (' + [string]$c2.err + ')' } elseif (-not $now) { $now = 'every address' }
    Write-DbSecLog @('After the restart the database still listens on ' + $now + '. Another option (service command line, another my.ini section) may set bind-address.')
    Res 'warn' 'NOT localhost-only - see log'
}
# the deploy step: firewall, own account, localhost only. Fails only when the app could not log in at all
function Invoke-DbSecurity {
    $script:DbSecNote = ''
    $conn = (Get-EnvValue 'DB_CONNECTION').ToLower()
    if ($conn -ne 'mysql' -and $conn -ne 'mariadb') { return (Res 'ok' 'skipped (database is not MySQL)') }
    $dbh = Get-EnvValue 'DB_HOST'; if (-not $dbh) { $dbh = '127.0.0.1' }
    $dbp = Get-EnvValue 'DB_PORT'; if (-not $dbp) { $dbp = '3306' }
    if (-not (Test-LocalDbHost $dbh)) {
        Write-LaunchLog ('database security skipped: DB_HOST in .env is ' + $dbh + ', not this PC (bind-address, firewall and DB account left as they are)')
        return (Res 'warn' 'skipped: DB_HOST is not this PC')
    }
    Wait-BgJobs @('db')
    if (-not (Test-Port $dbp)) { return (Res 'warn' 'database is not running - skipped') }
    $warns = @()
    # (b) no inbound firewall rule may let other devices reach the database
    $fw = Protect-DbFirewall ([int]$dbp)
    if ($fw.State -ne 'ok') { $warns += $fw.Detail }
    # (c) LSPDMS's own account: a working non-root login in .env is kept as it is
    $u = Get-EnvValue 'DB_USERNAME'; $w = Get-EnvValue 'DB_PASSWORD'
    $chk = Get-DbLoginCheck $dbh $dbp $u $w
    $refused = (-not $chk.ok) -and [string]$chk.stage -eq 'login' -and (@(1044, 1045, 1698) -contains [int]$chk.code)
    if (-not $chk.ok -and -not $refused) {
        # not a wrong login (no answer, PHP problem...): nothing is changed
        Write-DbSecLog @('The database could not be checked: ' + [string]$chk.err)
        return (Res 'warn' 'database could not be checked')
    }
    if ($refused -or -not $u -or $u -eq 'root') {
        $a = Ensure-AppDbAccount $dbh $dbp
        if ($a.State -ne 'ok') { return (Res 'fail' $a.Detail) }
        $chk = $a.Check; $u = Get-EnvValue 'DB_USERNAME'; $w = Get-EnvValue 'DB_PASSWORD'
    }
    # (a) localhost only
    $b = Ensure-DbLocalOnly $dbh $dbp $chk $u $w
    if ($b.State -ne 'ok') { $warns = @($b.Detail) + $warns }
    if ($warns.Count -gt 0) { return (Res 'warn' ('own account; ' + ($warns -join ', '))) }
    $script:DbSecNote = 'localhost only'
    Res 'ok' 'localhost only, own account'
}

# ---- services ----
function Get-ServerScript {
    $own = Join-Path $Project 'server.php'
    if (Test-Path -LiteralPath $own) { return $own }
    Join-Path $Project 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
}
function Start-WebServer {
    Wait-BgJobs @('warm')
    $srv = Get-ServerScript
    if (-not (Test-Path -LiteralPath $srv)) { return (Res 'fail' 'server.php not found (composer install)') }
    $opc = Get-OpcacheArgs
    $arg = ''
    if ($opc) { $arg = $opc + ' ' }
    # PHP's built-in web server (what "artisan serve" runs), started directly so the OPcache -d flags reach it.
    # Bound to 127.0.0.1 unless Team / network mode was chosen
    $arg += '-S ' + $BindHost + ':' + $AppPort + ' "' + $srv + '"'
    $p = Start-Tracked 'web' 'php' $arg (Join-Path $Logs 'serve.log') (Join-Path $Project 'public')
    Add-BgJob 'web' $script:CurStep 30 {
        param($j, $final)
        if (Test-Port $AppPort) {
            $d = 'online :' + $AppPort
            if ($LanIp) { $d = 'online ' + $LanIp + ':' + $AppPort }
            if ($j.Data.Opc) { $d += ' + OPcache' }
            return (Res 'ok' $d)
        }
        if ($j.Data.Proc.HasExited -or $final) { $script:FailLog = Join-Path $Logs 'serve.log'; return (Res 'fail' ('nothing listening on port ' + $AppPort)) }
        $null
    } @{ Proc = $p; Opc = [bool]$opc }
    Res 'bg' 'starting...'
}
function Start-Reverb {
    $rh = '127.0.0.1'
    if ($LanMode) { $rh = '0.0.0.0' }
    $p = Start-Tracked 'reverb' 'php' ('artisan reverb:start --host=' + $rh + ' --port=' + $ReverbPort + ' --no-interaction') (Join-Path $Logs 'reverb.log')
    Add-BgJob 'reverb' $script:CurStep 30 {
        param($j, $final)
        if (Test-Port $ReverbPort) { return (Res 'ok' ('online :' + $ReverbPort)) }
        if ($j.Data.Proc.HasExited -or $final) { $script:FailLog = Join-Path $Logs 'reverb.log'; return (Res 'warn' 'live chat offline - see reverb.log') }
        $null
    } @{ Proc = $p }
    Res 'bg' 'starting...'
}
function Get-SchedulerTask {
    try { return (Get-ScheduledTask -TaskName $SchedulerTask -ErrorAction Stop) } catch { return $null }
}
function Register-SchedulerTask {
    $vbs = Join-Path $Project 'scripts\windows\run-scheduler.vbs'
    if (-not (Test-Path -LiteralPath $vbs)) { return $false }
    try {
        $act = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ('//B //nologo "' + $vbs + '"') -WorkingDirectory $Project
        $trg = New-ScheduledTaskTrigger -Once -At ((Get-Date).Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
        $pri = New-ScheduledTaskPrincipal -UserId ($env:USERDOMAIN + '\' + $env:USERNAME) -LogonType Interactive -RunLevel Limited
        $set = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
        Register-ScheduledTask -TaskName $SchedulerTask -Action $act -Trigger $trg -Principal $pri -Settings $set -ErrorAction Stop | Out-Null
        return $true
    } catch { return $false }
}
$script:SchedMode = 'off'
function Start-Workers {
    $bits = @(); $state = 'ok'
    $script:SchedMode = 'off'
    if ($Scheduler -ne 'off') {
        $task = $null
        if ($Scheduler -ne 'work') { $task = Get-SchedulerTask }
        if (-not $task -and $Scheduler -eq 'task' -and (Register-SchedulerTask)) { $task = Get-SchedulerTask; $bits += 'scheduler task created' }
        if ($task -and [string]$task.State -ne 'Disabled') {
            $script:SchedMode = 'task'
            $acts = (@($task.Actions) | ForEach-Object { [string]$_.Arguments + ' ' + [string]$_.Execute }) -join ' '
            if ($acts.IndexOf($Project, [StringComparison]::OrdinalIgnoreCase) -lt 0) { $state = 'warn'; $bits += 'Windows task runs another folder' }
            elseif ($bits.Count -eq 0) { $bits += 'scheduler: Windows task' }
        } else {
            # no (enabled) Windows task: run the scheduler only while LSPDMS runs
            [void](Start-Tracked 'scheduler' 'php' 'artisan schedule:work --no-interaction' (Join-Path $Logs 'schedule.log'))
            $script:SchedMode = 'work'
            $bits += 'schedule:work'
        }
    } else { $bits += 'scheduler off' }
    # broadcasts use ShouldBroadcastNow and QUEUE_CONNECTION=sync, so normally no queue worker is needed
    $qc = (Get-EnvValue 'QUEUE_CONNECTION').ToLower()
    if (-not $qc) { $qc = 'database' }
    if ($qc -ne 'sync' -and $qc -ne 'null') {
        [void](Start-Tracked 'queue' 'php' 'artisan queue:work --tries=1 --sleep=1 --no-interaction' (Join-Path $Logs 'queue.log'))
        $bits += 'queue worker'
    } else { $bits += 'queue: sync' }
    Res $state ($bits -join ', ')
}
# is the scheduler alive?  task = the Windows task is enabled, work = the launcher's schedule:work process runs
function Test-SchedulerUp {
    if ($script:SchedMode -eq 'task') { $t = Get-SchedulerTask; return [bool]($t -and [string]$t.State -ne 'Disabled') }
    if ($script:SchedMode -eq 'work') { return [bool](Get-TrackedProc 'scheduler') }
    $false
}

# GET without following redirects; returns the HTTP status (0 = no answer). Never uses a proxy; keeps the screen moving
function Get-HttpStatus($u, $timeoutMs = 8000) {
    try {
        $req = [Net.HttpWebRequest]::Create($u)
        $req.AllowAutoRedirect = $false; $req.Proxy = $null; $req.Timeout = $timeoutMs; $req.Method = 'GET'
        $ar = $req.BeginGetResponse($null, $null)
        $t = Get-Date
        while (-not $ar.IsCompleted) {
            if (((Get-Date) - $t).TotalMilliseconds -gt $timeoutMs) { $req.Abort(); return 0 }
            Pump
        }
        $resp = $req.EndGetResponse($ar)
        $code = [int]$resp.StatusCode; $resp.Close()
        return $code
    } catch {
        $we = $_.Exception
        while ($we -and -not ($we -is [Net.WebException])) { $we = $we.InnerException }
        if ($we -and $we.Response) { $code = [int]$we.Response.StatusCode; try { $we.Response.Close() } catch {}; return $code }
        return 0
    }
}
function Invoke-HealthCheck {
    Wait-BgJobs
    $bad = $script:Steps | Where-Object { $_.State -eq 'fail' } | Select-Object -First 1
    if ($bad) { return (Res 'warn' ('see: ' + $bad.Name)) }
    $base = 'http://127.0.0.1:' + $AppPort
    # /up = Laravel's health route (boots the app); /login = a real page (200, or 302 with a session)
    $up = 0; $t = Get-Date
    while (((Get-Date) - $t).TotalSeconds -lt 25) {
        $up = Get-HttpStatus ($base + '/up') 10000
        if ($up -eq 200) { break }
        Pump
    }
    $sw = [Diagnostics.Stopwatch]::StartNew()
    $lg = Get-HttpStatus ($base + '/login') 15000
    $ms = $sw.ElapsedMilliseconds
    $bits = @('/up ' + $up, '/login ' + $lg + ' in ' + $ms + ' ms')
    $warn = @()
    if ($up -ne 200) { $warn += '/up' }
    if ($lg -ne 200 -and $lg -ne 302) { $warn += '/login' }
    if ($UseReverb -and -not (Test-Port $ReverbPort)) { $warn += 'live chat' }
    if ($UseMySQL -and -not (Test-Port $MySQLPort)) { $warn += 'database' }
    if ($Scheduler -ne 'off' -and -not (Test-SchedulerUp)) { $warn += 'scheduler' }
    if ($script:FePlan -ne 'build' -and -not (Test-VitePort)) { $warn += 'Vite' }
    Write-LaunchLog ('health: ' + ($bits -join ', '))
    if ($warn.Count -gt 0) {
        $script:FailLog = Join-Path $Logs 'serve.log'
        return (Res 'warn' ('check ' + ($warn -join ', ') + ' | ' + ($bits -join ', ')))
    }
    Res 'ok' ($bits -join ', ')
}
# the LAN firewall rule is only wanted while Team / network mode runs
function Remove-LanFirewall {
    try { if (Get-NetFirewallRule -DisplayName 'LSPDMS' -ErrorAction SilentlyContinue) { Remove-NetFirewallRule -DisplayName 'LSPDMS' -ErrorAction SilentlyContinue } } catch {}
}

# ------------------- first launch: master (Head Secretary) account -------------------
# Runs a small PHP/PDO script with the same DB login Laravel uses (from .env).
# The form data goes in as ONE base64 JSON environment variable: names with n-tilde/accents stay intact
# and the password never appears on a command line. The variables are removed again right after.
function Invoke-MasterDb($mode, $data = $null) {
    $conn = (Get-EnvValue 'DB_CONNECTION').ToLower()
    if ($conn -ne 'mysql' -and $conn -ne 'mariadb') { return 'SKIP' }
    $name = Get-EnvValue 'DB_DATABASE'
    if (-not $name) { return 'ERR: DB_DATABASE is empty in .env' }
    $dbh = Get-EnvValue 'DB_HOST'; if (-not $dbh) { $dbh = '127.0.0.1' }
    $dbp = Get-EnvValue 'DB_PORT'; if (-not $dbp) { $dbp = '3306' }
    $env:LSP_H = $dbh; $env:LSP_P = $dbp; $env:LSP_U = (Get-EnvValue 'DB_USERNAME'); $env:LSP_W = (Get-EnvValue 'DB_PASSWORD'); $env:LSP_D = $name
    $env:LSP_MODE = $mode
    if ($data) {
        $json = ($data | ConvertTo-Json -Compress)
        $env:LSP_DATA = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($json))
    }
    $code = @'
<?php
$h = getenv("LSP_H"); $p = getenv("LSP_P"); $u = getenv("LSP_U"); $w = getenv("LSP_W"); $d = getenv("LSP_D");
$mode = getenv("LSP_MODE");
try {
  $pdo = new PDO("mysql:host=$h;port=$p;dbname=$d;charset=utf8mb4", $u, $w, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $has = (int)$pdo->query("SELECT COUNT(*) FROM login_user WHERE tungkulin = 'Head Secretary'")->fetchColumn();
  if ($mode === "check") { echo ($has > 0 ? "RESULT:HAS" : "RESULT:NONE"); exit(0); }
  if ($has > 0) { echo "RESULT:HAS"; exit(0); }
  $x = json_decode(base64_decode(getenv("LSP_DATA")), true);
  if (!is_array($x)) { echo "ERR: no data received"; exit(1); }
  $st = $pdo->prepare("SELECT COUNT(*) FROM login_user WHERE gmail = ?");
  $st->execute([$x["gmail"]]);
  if ((int)$st->fetchColumn() > 0) { echo "ERR: that email is already used by another account"; exit(1); }
  $hash = password_hash($x["password"], PASSWORD_BCRYPT, ["cost" => 12]);
  $ins = $pdo->prepare("INSERT INTO login_user (gmail, password, tungkulin, is_verified, first_name, last_name, dashboard_allow, form_generator_allow, Delistment_allow, Inquire_Allow, tarheta_status_allow, filter_bnh_helper_allow) VALUES (?, ?, 'Head Secretary', 1, ?, ?, 1, 1, 1, 1, 1, 1)");
  $ins->execute([$x["gmail"], $hash, $x["first"], $x["last"]]);
  echo "RESULT:CREATED";
} catch (Throwable $e) { echo "ERR: " . $e->getMessage(); exit(1); }
'@
    $php = "$Logs\master.php"
    $log = "$Logs\master.log"
    Remove-Item $log, "$log.err" -ErrorAction SilentlyContinue
    Set-Content -Path $php -Value $code -Encoding ASCII
    $out = ''
    try {
        $p = Start-Hidden 'php' ('"' + $php + '"') $log
        Wait-Proc $p 30
        $out = ([string](Get-Content $log -Raw)).Trim()
        if (-not $out) { $out = 'ERR: ' + (([string](Get-Content "$log.err" -Raw)).Trim()) }
    } catch {
        $out = 'ERR: ' + $_.Exception.Message
    } finally {
        foreach ($n in 'LSP_H', 'LSP_P', 'LSP_U', 'LSP_W', 'LSP_D', 'LSP_MODE', 'LSP_DATA') { Remove-Item "Env:\$n" -ErrorAction SilentlyContinue }
        Remove-Item $php, $log, "$log.err" -ErrorAction SilentlyContinue
    }
    return $out
}

# the setup screen. Returns @{ gmail; password; first; last }, or $null when cancelled with Esc
function Read-MasterAccount($seed = $null, $notice = '') {
    $fields = @(
        @{ L = 'Email (Gmail)';     V = ''; Mask = $false; Max = 100 },
        @{ L = 'First name';        V = ''; Mask = $false; Max = 100 },
        @{ L = 'Last name';         V = ''; Mask = $false; Max = 100 },
        @{ L = 'Password';          V = ''; Mask = $true;  Max = 64 },
        @{ L = 'Confirm password';  V = ''; Mask = $true;  Max = 64 }
    )
    if ($seed) { $fields[0].V = [string]$seed.gmail; $fields[1].V = [string]$seed.first; $fields[2].V = [string]$seed.last }
    $last = $fields.Count - 1
    $idx = 0; $msg = $notice; $show = $false
    Wipe
    while ($true) {
        Frame 'Yellow' 'FIRST SETUP' {
            Row
            Row (Pt '  Create the master account (Head Secretary)' 'White')
            Row (Pt '  No Head Secretary exists yet. This account is saved to the' 'Gray')
            Row (Pt '  database and is used to log in after setup.' 'Gray')
            Row
            for ($n = 0; $n -lt $fields.Count; $n++) {
                $fd = $fields[$n]
                $txt = [string]$fd.V
                if ($fd.Mask -and -not $show) { $txt = '*' * $txt.Length }
                if ($n -eq $idx) { $txt = $txt + '_' }
                if ($txt.Length -gt 44) { $txt = $txt.Substring($txt.Length - 44) }
                if ($n -eq $idx) {
                    Row (Pt '  > ' 'Cyan') (Pt ($fd.L.PadRight(18)) 'White') (Pt ($txt.PadRight(44)) 'Black' 'Cyan')
                } else {
                    Row (Pt '    ' 'Gray') (Pt ($fd.L.PadRight(18)) 'Gray') (Pt ($txt.PadRight(44)) 'White' 'DarkGray')
                }
                Row
            }
            Row (Pt ('  ' + (Fit $msg 68).PadRight(68)) 'Red')
            Row
            HintRow 'Enter / Tab  next field        Up  previous field'
            HintRow 'F2  show or hide password      Esc  cancel'
            HintRow 'Password: at least 8 characters.  Enter on the last field = create.'
        }
        $ki = [Console]::ReadKey($true)
        $key = [string]$ki.Key
        if ($key -eq 'Escape') { return $null }
        elseif ($key -eq 'F2') { $show = -not $show }
        elseif ($key -eq 'UpArrow') { $idx = ($idx + $last) % ($last + 1) }
        elseif ($key -eq 'DownArrow') { if ($idx -lt $last) { $idx++ } }
        elseif ($key -eq 'Tab') {
            if (($ki.Modifiers -band [ConsoleModifiers]::Shift) -ne 0) { $idx = ($idx + $last) % ($last + 1) }
            else { $idx = ($idx + 1) % ($last + 1) }
        }
        elseif ($key -eq 'Backspace') {
            $cur = [string]$fields[$idx].V
            if ($cur.Length -gt 0) { $fields[$idx].V = $cur.Substring(0, $cur.Length - 1) }
        }
        elseif ($key -eq 'Enter') {
            if ($idx -lt $last) { $idx++; continue }
            $mail = ([string]$fields[0].V).Trim().ToLower()
            $fn = ([string]$fields[1].V).Trim()
            $ln = ([string]$fields[2].V).Trim()
            $pw = [string]$fields[3].V
            $pw2 = [string]$fields[4].V
            if ($mail -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$') { $msg = 'Enter a valid email address.'; $idx = 0 }
            elseif (-not $fn) { $msg = 'First name is required.'; $idx = 1 }
            elseif (-not $ln) { $msg = 'Last name is required.'; $idx = 2 }
            elseif ($pw.Length -lt 8) { $msg = 'Password must be at least 8 characters.'; $idx = 3 }
            elseif ($pw -ne $pw2) { $msg = 'Passwords do not match - retype the confirmation.'; $fields[4].V = ''; $idx = 4 }
            else { return @{ gmail = $mail; password = $pw; first = $fn; last = $ln } }
        }
        elseif ($ki.KeyChar -and -not [char]::IsControl($ki.KeyChar) -and ([string]$fields[$idx].V).Length -lt $fields[$idx].Max) {
            $fields[$idx].V = [string]$fields[$idx].V + [string]$ki.KeyChar
            $msg = ''
        }
    }
}

# deploy step: if the database has no Head Secretary yet, make the person create one now
function Ensure-MasterAccount {
    if (-not $RequireMasterAccount) { return (Res 'ok' 'check disabled') }
    $st = Invoke-MasterDb 'check'
    if ($st -eq 'SKIP') { return (Res 'ok' 'skipped (database is not MySQL)') }
    if ($st -match 'RESULT:HAS') { return (Res 'ok' 'Head Secretary account found') }
    if ($st -notmatch 'RESULT:NONE') {
        # say WHY the check failed (the full text goes to master-check.log)
        $why = ([string]$st -replace '^ERR:\s*', '').Trim()
        try { Set-Content -Path "$Logs\master-check.log" -Value ('Account check failed: ' + [string]$st) -Encoding UTF8 } catch {}
        $script:FailLog = "$Logs\master-check.log"
        if ($why -match 'could not find driver') { return (Res 'warn' 'PHP pdo_mysql is not enabled') }
        if ($why -match 'Access denied') { return (Res 'warn' 'DB login refused - check .env DB_USERNAME / DB_PASSWORD') }
        if ($why -match 'Unknown database') { return (Res 'warn' 'database does not exist - migrate did not run') }
        if ($why -match '42S02|Base table or view not found|doesn.t exist') { return (Res 'warn' 'login_user table missing - migrate failed') }
        if ($why -match 'refused|2002|2006|gone away|timed out|getaddrinfo') { return (Res 'warn' 'cannot reach MySQL - is it running?') }
        if (-not $why) { $why = 'no answer from PHP' }
        return (Res 'warn' ('cannot check accounts: ' + (Fit $why 44)))
    }

    $seed = $null; $notice = ''
    while ($true) {
        $acct = Read-MasterAccount $seed $notice
        if (-not $acct) { Wipe; return (Res 'fail' 'master account setup was cancelled') }
        Wipe
        $r = Invoke-MasterDb 'create' $acct
        if ($r -match 'RESULT:CREATED') { Wipe; return (Res 'ok' ('created: ' + $acct.gmail)) }
        if ($r -match 'RESULT:HAS') { Wipe; return (Res 'ok' 'Head Secretary account found') }
        $m = [regex]::Match($r, 'ERR: ?(.*)')
        if ($m.Success) { $notice = $m.Groups[1].Value } else { $notice = 'Could not save the account - try again.' }
        $seed = $acct
    }
}

function Mk($name, [scriptblock]$act) {
    [pscustomobject]@{ Name = $name; State = 'pending'; Detail = ''; Action = $act }
}

# --------------------------------- the steps ---------------------------------
function Build-Steps {
    $l = @()

    $l += (Mk 'Locate project files' {
        if (Test-Path -LiteralPath (Join-Path $Project 'artisan')) { Set-Location -LiteralPath $Project; Set-LauncherPaths; [void](Fix-EnvDatabase); Res 'ok' $Project }
        else { Res 'fail' "artisan not found - set $Project at top" }
    })

    if ($LanMode) {
        $l += (Mk 'Detect network address' {
            $script:BindHost = '0.0.0.0'
            $note = ''
            if ($RenewIp -and (Test-Admin)) {
                $p = Start-Hidden 'cmd.exe' '/c ipconfig /release' "$Logs\ip-release.log"; Wait-Proc $p 20
                $p = Start-Hidden 'cmd.exe' '/c ipconfig /renew' "$Logs\ip-renew.log"; Wait-Proc $p 45
                $note = ' (renewed)'
            }
            # works on a hotspot with no data: only a local address is needed, not internet
            $t = Get-Date; $ip = $null
            while (((Get-Date) - $t).TotalSeconds -lt 8) {
                $ip = Get-LanIp
                if ($ip) { break }
                Pump
            }
            if (-not $ip -and $AutoFixNetwork) {
                if (Test-Admin) {
                    $script:NetNote = ''
                    $ip = Repair-Network
                    if ($ip) { $note = $script:NetNote }
                } else { return (Res 'warn' 'no network - run as Administrator to auto-fix') }
            }
            if ($ip) {
                $script:LanIp = $ip; $script:BindHost = '0.0.0.0'; $script:LanUrl = "http://${ip}:$AppPort"
                Res 'ok' ($ip + ' [' + $script:LanIf + ']' + $note)
            } else { Res 'warn' 'no IP yet - will keep looking' }
        })
        if ($OpenFirewall) {
            $l += (Mk 'Open firewall for network' {
                if (-not (Test-Admin)) { return (Res 'warn' 'not Administrator - may be blocked') }
                try {
                    # remove "Block" rules Windows creates when "Allow access" is cancelled for php/node
                    Get-NetFirewallApplicationFilter -ErrorAction SilentlyContinue |
                        Where-Object { $_.Program -match 'php|node' } |
                        Get-NetFirewallRule -ErrorAction SilentlyContinue |
                        Where-Object { $_.Action -eq 'Block' -and $_.Direction -eq 'Inbound' } |
                        Remove-NetFirewallRule -ErrorAction SilentlyContinue
                    Remove-NetFirewallRule -DisplayName 'LSPDMS' -ErrorAction SilentlyContinue
                    $ports = @($AppPort); if ($UseReverb) { $ports += $ReverbPort }
                    # only devices on the same local network (subnet) may connect; the rule is removed again on shutdown
                    New-NetFirewallRule -DisplayName 'LSPDMS' -Direction Inbound -Action Allow -Protocol TCP -LocalPort $ports -RemoteAddress LocalSubnet -Profile Any -ErrorAction Stop | Out-Null
                    Res 'ok' ('ports ' + ($ports -join ', ') + ' open to local network')
                } catch { Res 'warn' 'could not edit firewall' }
            })
        }
    }

    if ($LanMode) {
        $l += (Mk 'Prepare PC for team use' {
            $bits = @(); $warn = $false
            try {
                $pr = Get-NetConnectionProfile -InterfaceAlias $script:LanIf -ErrorAction Stop
                if ($pr.NetworkCategory -eq 'Public') { $warn = $true } else { $bits += 'Private' }
            } catch {}
            if ($KeepAwake) { try { [void][Lsp.Pwr]::SetThreadExecutionState([uint32]2147483649); $bits += 'awake' } catch {} }
            if (Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue) { $bits += 'laptop: plug in' }
            if ($warn) { return (Res 'warn' 'Public net - set it to Private') }
            Res 'ok' (($bits -join ', '))
        })
    }

    $l += (Mk 'Stop old LSPDMS processes' {
        # processes an earlier launcher run started (pids.json) are stopped, except a database / Vite server worth keeping;
        # the web + live-chat ports are then freed (only PHP / Node programs are stopped there - never every node.exe on the PC)
        $adopt = @('mysql')
        if ($script:FePlan -eq 'keep-dev') { $adopt += 'vite' }
        $n = Resolve-PidFile $adopt
        if (-not $LanMode) { Remove-LanFirewall }
        $e = Clear-Port $AppPort
        if ($e) { return (Res 'fail' ($e + ' - close it, then Retry')) }
        $warns = @()
        if ($UseReverb) { $e = Clear-Port $ReverbPort; if ($e) { $warns += $e } }
        # the Vite port: only a Vite the launcher started (pids.json, stopped just above) is ever ended. A dev server
        # someone started by hand (npx vite) is left running - in compiled-assets mode its hot file is handled by the front-end step
        if ($script:FePlan -eq 'start-dev' -or ($script:FePlan -eq 'build' -and $n -gt 0)) {
            $t = Get-Date
            while ((Test-VitePort) -and ((Get-Date) - $t).TotalSeconds -lt 6) { Pump }
            if ($script:FePlan -eq 'start-dev' -and (Test-VitePort)) { return (Res 'fail' ('port ' + $VitePort + ' is used by another program - close it, then Retry')) }
        }
        if ($warns.Count -gt 0) { return (Res 'warn' ($warns -join '; ')) }
        if ($n -gt 0) { Res 'ok' ("ports free, $n old process(es) stopped") } else { Res 'ok' 'ports free' }
    })

    $l += (Mk 'Check app settings (.env)' { Invoke-EnvCheck })

    if ($UseMySQL) {
        $l += (Mk 'Start database (MySQL)' { Start-Database })
    }

    $l += (Mk 'Check PHP + JS packages' { Invoke-PackageCheck })

    $l += (Mk 'Front-end assets (Vite)' { Invoke-FrontEnd })

    $l += (Mk 'Refresh framework caches' { Invoke-Caches })

    if ($UseMySQL -and $SecureDatabase) {
        $l += (Mk 'Database security' { Invoke-DbSecurity })
    }

    if ($AutoMigrate) {
        $l += (Mk 'Back up + migrate database' {
            Wait-BgJobs @('db')
            if ($UseMySQL -and -not (Test-Port $MySQLPort)) { return (Res 'warn' 'database is not running - skipped') }
            Invoke-Migrate
        })
    }

    if ($RequireMasterAccount) {
        $l += (Mk 'Set up master account' { Wait-BgJobs @('db'); Ensure-MasterAccount })
    }

    if ($UseReverb) {
        $l += (Mk 'Start live chat (Reverb)' { Start-Reverb })
    }

    $l += (Mk 'Start scheduler + workers' { Start-Workers })

    $l += (Mk 'Start application server' { Start-WebServer })

    $l += (Mk 'Run health checks' { Invoke-HealthCheck })

    $l += (Mk 'Launch browser' {
        $target = Get-OpenUrl
        if ($OpenBrowser) { Start-Process $target; Res 'ok' $target } else { Res 'ok' 'skipped' }
    })

    $l
}

# the database is an installed Windows service: start it (or let it finish starting) and wait for its port.
# It is not tracked in pids.json - Windows owns it, so "Shut down" leaves it running
function Start-DbService($name) {
    $s = Get-Service -Name $name -ErrorAction SilentlyContinue
    if (-not $s) { return (Res 'warn' ('database service ' + $name + ' not found')) }
    if ($s.StartType -eq 'Disabled') { return (Res 'warn' ('database service ' + $name + ' is disabled - enable it in services.msc')) }
    if ($s.Status -ne 'Running' -and $s.Status -ne 'StartPending') {
        try { $s.Start() } catch { return (Res 'warn' ('could not start service ' + $name + ': ' + $_.Exception.Message)) }
    }
    Add-BgJob 'db' $script:CurStep 90 {
        param($j, $final)
        if (Test-Port $MySQLPort) { return (Res 'ok' ('service ' + $j.Data.Name + ' online :' + $MySQLPort)) }
        $st = ''
        try { $st = [string](Get-Service -Name $j.Data.Name -ErrorAction Stop).Status } catch {}
        if ($st -eq 'Stopped' -and ((Get-Date) - $j.T0).TotalSeconds -gt 8) { return (Res 'warn' ('service ' + $j.Data.Name + ' stopped - see its error log')) }
        if ($final) { return (Res 'warn' ('service ' + $j.Data.Name + ' gave no response on :' + $MySQLPort)) }
        $null
    } @{ Name = $name }
    Res 'bg' ('starting service ' + $name + '...')
}
# starts the database when it is not running yet. It boots in the background while the next steps run;
# "Back up + migrate database" waits for it
function Start-Database {
    if (Test-Port $MySQLPort) {
        if (Get-TrackedProc 'mysql') { return (Res 'ok' "running :$MySQLPort (started by LSPDMS)") }
        return (Res 'ok' "already running :$MySQLPort")
    }
    $m = Find-MySqld
    if (-not $m) { return (Res 'warn' 'MySQL not found - open Dependencies') }
    if ($m.Service) { return (Start-DbService $m.Service) }
    $a = '--console'
    $cfgTxt = ''
    if (Test-Path -LiteralPath $m.Cfg) {
        $a = "--defaults-file=`"$($m.Cfg)`" --console"
        try { $cfgTxt = [IO.File]::ReadAllText($m.Cfg) } catch {}
    }
    # the app reaches the database on this PC only: no other device can connect to it (see Invoke-DbSecurity)
    if ($SecureDatabase -and (Test-LocalDbHost (Get-EnvValue 'DB_HOST'))) { $a += ' --bind-address=127.0.0.1' }
    # where does this MySQL keep its data, and which port does it really use?
    $dataDir = Join-Path (Split-Path -Parent (Split-Path -Parent $m.Exe)) 'data'
    $dm = [regex]::Match($cfgTxt, '(?im)^[ \t]*datadir[ \t]*=[ \t]*"?([^"\r\n]+?)"?[ \t\r]*$')
    if ($dm.Success) { $dataDir = $dm.Groups[1].Value.Trim().Replace('/', '\') }
    $cfgPort = ''
    $pm = [regex]::Match($cfgTxt, '(?ims)\[mysqld\][^\[]*?^[ \t]*port[ \t]*=[ \t]*(\d+)')
    if ($pm.Success) { $cfgPort = $pm.Groups[1].Value }
    # a brand-new MySQL has no data yet and exits at once: create it first (only when the folder is missing or empty)
    $hasData = (Test-Path -LiteralPath (Join-Path $dataDir 'mysql'))
    $emptyData = (-not (Test-Path -LiteralPath $dataDir)) -or (@(Get-ChildItem -LiteralPath $dataDir -Force -ErrorAction SilentlyContinue).Count -eq 0)
    if (-not $hasData -and $emptyData) {
        $ia = '--initialize-insecure --console'
        if (Test-Path -LiteralPath $m.Cfg) { $ia = "--defaults-file=`"$($m.Cfg)`" --initialize-insecure --console" }
        else { $ia = "--initialize-insecure --console --datadir=`"$dataDir`"" }
        $ip = Start-Hidden $m.Exe $ia (Join-Path $Logs 'mysql-init.log')
        Wait-Proc $ip 180
    }
    $p = Start-Tracked 'mysql' $m.Exe $a (Join-Path $Logs 'mysql.log')
    $script:StartedMySQL = $true
    Add-BgJob 'db' $script:CurStep 60 {
        param($j, $final)
        $p = $j.Data.Proc
        if (Test-Port $MySQLPort) { return (Res 'ok' "online :$MySQLPort") }
        if (-not $p.HasExited -and -not $final) { return $null }
        $script:FailLog = Join-Path $Logs 'mysql.log'
        if ($j.Data.CfgPort -and $j.Data.CfgPort -ne [string]$MySQLPort) { return (Res 'warn' ('MySQL uses port ' + $j.Data.CfgPort + ', launcher expects ' + $MySQLPort)) }
        $err = ''
        $em = @(Get-LogTail (Join-Path $Logs 'mysql.log') 40 | Where-Object { $_ -match '\[ERROR\]' } | Select-Object -Last 1)
        if ($em.Count -gt 0) { $err = ([string]$em[0] -replace '^.*\[ERROR\]\s*(\[[^\]]*\]\s*)*', '').Trim() }
        if ($p.HasExited) {
            $script:StartedMySQL = $false
            if ($err) { return (Res 'warn' ('MySQL stopped: ' + $err)) }
            return (Res 'warn' 'MySQL stopped - see mysql.log')
        }
        Res 'warn' 'no response on port'
    } @{ Proc = $p; CfgPort = $cfgPort }
    Res 'bg' 'starting...'
}

function Do-Deploy {
    $script:Theme = 'Cyan'; $script:Badge = 'DEPLOYING'; $script:Banner = $null
    Update-PortsFromEnv
    $script:FePlan = Get-FrontEndPlan
    $script:BgJobs = @(); $script:LastBackup = $null; $script:NpmJob = $null; $script:NpmStep = $null
    $script:Steps = Build-Steps
    $script:pct = 0; $script:spin = 0; $script:T0 = Get-Date; $script:FailLog = $null
    $modeTxt = 'this PC only'
    if ($LanMode) { $modeTxt = 'team / network' }
    Write-LaunchLog ('=== launch: ' + $modeTxt + ', front-end: ' + $script:FePlan + ', project: ' + $Project)
    Wipe
    Render-Steps $script:Theme $script:Badge
    foreach ($s in $script:Steps) {
        $script:CurStep = $s
        $s.State = 'run'; $s.Detail = 'working...'
        Pump
        $r = $null
        try {
            $out = @(& $s.Action)
            $r = $out | Where-Object { $_ -and $_.PSObject.Properties['State'] } | Select-Object -Last 1
        } catch { $r = Res 'fail' $_.Exception.Message }
        if (-not $r) { $r = Res 'ok' '' }
        $s.State = $r.State; $s.Detail = $r.Detail
        if ($r.State -ne 'bg') { Write-LaunchLog ('[' + ([string]$r.State).ToUpper() + '] ' + $s.Name + ' - ' + $r.Detail) }
        Pump
        $bad = $script:Steps | Where-Object { $_.State -eq 'fail' } | Select-Object -First 1
        if ($bad) {
            # let builds / installs that are already running finish cleanly before the failure screen
            Wait-BgJobs @('build', 'warm')
            if ($script:NpmJob) { [void](Wait-NpmInstall) }
            $script:FailStep = $bad
            return 'fail'
        }
    }
    Wait-BgJobs
    $bad = $script:Steps | Where-Object { $_.State -eq 'fail' } | Select-Object -First 1
    if ($bad) { $script:FailStep = $bad; return 'fail' }
    $secs = [math]::Round(((Get-Date) - $script:T0).TotalSeconds, 1)
    Write-LaunchLog ('ready in ' + $secs + ' s: ' + (Get-OpenUrl))
    $script:Banner = @{ T = ('  DEPLOYMENT COMPLETE  ' + $secs + ' s  '); F = 'Black'; B = 'Green' }
    for ($i = 0; $i -lt 10; $i++) { Pump }
    return 'ok'
}

# --------------------------------- screens ---------------------------------
function Show-Splash {
    Wipe
    $msgs = @('Loading core modules...', 'Linking services...', 'Checking environment...', 'Ready.')
    for ($i = 0; $i -le 12; $i++) {
        $pp = $i / 12 * 100
        $m = $msgs[[math]::Min(3, [int][math]::Floor($i / 3.25))]
        Frame 'Cyan' 'BOOT' {
            Row
            Row (Pt ('  ' + $m.PadRight(60)) 'Gray')
            Row
            BarRow $pp 'Cyan'
            Row
        }
        Start-Sleep -Milliseconds 25
    }
    Start-Sleep -Milliseconds 120
}

function SvcRow($label, $port, $up, $note = '') {
    $c = 'Red'; $st = 'OFFLINE'
    if ($up) { $c = 'Green'; $st = 'ONLINE' }
    $where = $note
    if ($port) { $where = ":$port"; if ($note) { $where += '  ' + $note } }
    Row (Pt '  ') (Pt $G.Dot $c) (Pt ' ') (Pt ($label.PadRight(28)) 'White') (Pt ($st.PadRight(10)) $c) (Pt (Fit $where 30) 'DarkGray')
}

function Show-Online {
    $deg = @($script:Steps | Where-Object { $_.State -eq 'warn' }).Count -gt 0
    $theme = 'Green'; $badge = 'ONLINE'; $btxt = '  ALL SYSTEMS ONLINE  '; $bfg = 'Black'
    if ($deg) { $theme = 'Yellow'; $badge = 'DEGRADED'; $btxt = '  ONLINE WITH WARNINGS  ' }
    $up = Get-Date
    $stat = @{ web = $true; chat = $true; db = $true; sched = $true; vite = $true }
    $last = [datetime]::MinValue
    $lastSched = [datetime]::MinValue
    $firstWarn = $script:Steps | Where-Object { $_.State -eq 'warn' } | Select-Object -First 1
    $schedNote = 'Windows task'
    if ($script:SchedMode -eq 'work') { $schedNote = 'schedule:work' }
    $opcNote = ''
    if ($script:OpcacheArgs) { $opcNote = 'OPcache on' }
    $logsTxt = $Logs
    if ($Logs.StartsWith($Project, [StringComparison]::OrdinalIgnoreCase)) { $logsTxt = $Logs.Substring($Project.Length).TrimStart('\') }
    $lastNet = [datetime]::MinValue
    $lastFix = [datetime]::MinValue
    $netMsg = ''
    $onItems = @((BarItem 'O' 'Open browser' 'Cyan' 'open'), (BarItem 'R' 'Restart' 'Yellow' 'restart'), (BarItem 'M' 'Migrate' 'Cyan' 'migrate'), (BarItem 'S' 'Switch mode' 'Cyan' 'switch'), (BarItem 'Q' 'Shut down' 'Red' 'quit'))
    $onSel = 0
    Wipe
    while ($true) {
        if (((Get-Date) - $last).TotalSeconds -ge 3) {
            $stat.web = Test-Port $AppPort
            if ($UseReverb) { $stat.chat = Test-Port $ReverbPort }
            if ($UseMySQL)  { $stat.db   = Test-Port $MySQLPort }
            if ($script:FePlan -ne 'build') { $stat.vite = Test-VitePort }
            $last = Get-Date
        }
        if ($Scheduler -ne 'off' -and ((Get-Date) - $lastSched).TotalSeconds -ge 20) { $stat.sched = Test-SchedulerUp; $lastSched = Get-Date }
        # network watch: simply find the CURRENT ip every few seconds (no resetting)
        if ($LanMode -and ((Get-Date) - $lastNet).TotalSeconds -ge 8) {
            $lastNet = Get-Date
            $cur = Get-LanIp
            if ($cur -and $cur -ne $script:LanIp) {
                if ($script:LanIp) { $netMsg = "Address changed, now: $cur" } else { $netMsg = "Network found: $cur" }
                $script:LanIp = $cur; $script:LanUrl = "http://${cur}:$AppPort"
                $ui.WindowTitle = "LSPDMS - ONLINE - $cur"
            } elseif (-not $cur -and $script:LanIp) {
                if ($AutoFixNetwork -and (Test-Admin) -and ((Get-Date) - $lastFix).TotalSeconds -ge 180) {
                    $script:Quiet = $true; $script:NetNote = ''
                    $new = Repair-Network
                    $lastFix = Get-Date
                    $script:Quiet = $false
                    if ($new) { $script:LanIp = $new; $script:LanUrl = "http://${new}:$AppPort"; $netMsg = "Address restored: $new" }
                    else { $script:LanIp = $null; $script:LanUrl = $null; $netMsg = 'Network lost - waiting for it to return' }
                    Wipe
                } else {
                    $script:LanIp = $null; $script:LanUrl = $null
                    $netMsg = 'Network lost - waiting for it to return'
                    $ui.WindowTitle = "LSPDMS - ONLINE - $(Get-OpenUrl)"
                }
            }
        }
        $upt = ((Get-Date) - $up).ToString('hh\:mm\:ss')
        Frame $theme $badge {
            Row
            BannerRow $btxt $bfg $theme
            Row
            Row (Pt '  Your LSPDMS is at:' 'Gray')
            if ($LanUrl) {
                Row (Pt ('  ' + $G.Arrow + ' ') $theme) (Pt $LanUrl 'White') (Pt '   (team / network)' 'DarkGray')
            } else {
                Row (Pt ('  ' + $G.Arrow + ' ') $theme) (Pt $Url 'White') (Pt '   (this PC only)' 'DarkGray')
                if ($LanMode) { Row (Pt '  ! No network found - this PC only' 'Yellow') }
            }
            if ($netMsg) { Row (Pt ('  ! ' + (Fit $netMsg 68)) 'Yellow') }
            elseif ($firstWarn) { Row (Pt ('  ! ' + (Fit ($firstWarn.Name + ': ' + $firstWarn.Detail) 68)) 'Yellow') }
            else { Row }
            SvcRow 'Web server (Laravel)' $AppPort $stat.web $opcNote
            if ($UseReverb) { SvcRow 'Live chat (Reverb)' $ReverbPort $stat.chat }
            if ($UseMySQL)  { SvcRow 'Database (MySQL)' $MySQLPort $stat.db $script:DbSecNote }
            if ($Scheduler -ne 'off') { SvcRow 'Scheduler (backups)' '' $stat.sched $schedNote }
            if ($script:FePlan -ne 'build') { SvcRow 'Vite dev server' $VitePort $stat.vite 'live reload' }
            Row
            Row (Pt '  Uptime  ' 'DarkGray') (Pt $upt.PadRight(12) 'White') (Pt 'Logs  ' 'DarkGray') (Pt (Fit $logsTxt 46) 'Gray')
            if ($script:LastBackup) { Row (Pt '  Backup  ' 'DarkGray') (Pt (Fit ([IO.Path]::GetFileName($script:LastBackup)) 60) 'Gray') }
            Row
            SepLine
            ActionBar $onItems $onSel
            HintRow ($G.Left + '  ' + $G.Right + '  move          Enter  select')
        }
        $moved = $false
        while ([Console]::KeyAvailable) {
            $k = [Console]::ReadKey($true).Key
            $cmd = ''
            if ($k -eq 'LeftArrow' -or $k -eq 'UpArrow') { $onSel = ($onSel + $onItems.Count - 1) % $onItems.Count; $moved = $true }
            elseif ($k -eq 'RightArrow' -or $k -eq 'DownArrow') { $onSel = ($onSel + 1) % $onItems.Count; $moved = $true }
            elseif ($k -eq 'Enter') { $cmd = $onItems[$onSel].Id }
            if ($cmd -eq 'open') { Start-Process (Get-OpenUrl) }
            elseif ($cmd -eq 'restart') { return 'restart' }
            elseif ($cmd -eq 'switch') { return 'switch' }
            elseif ($cmd -eq 'migrate') {
                $script:Quiet = $true
                try {
                    $mr = @(Invoke-Migrate) | Where-Object { $_ -and $_.PSObject.Properties['State'] } | Select-Object -Last 1
                    if ($mr) { $netMsg = 'Migrate: ' + $mr.Detail } else { $netMsg = 'Migrate finished' }
                } finally { $script:Quiet = $false }
            }
            elseif ($cmd -eq 'quit') { return 'quit' }
        }
        if ($moved) { continue }
        Start-Sleep -Milliseconds 250
    }
}

function Show-Fail {
    $s = $script:FailStep
    $tail = @(Get-LogTail $script:FailLog 8)
    $fItems = @((BarItem 'R' 'Retry' 'Yellow' 'retry'), (BarItem 'L' 'Open log' 'Cyan' 'log'), (BarItem 'Q' 'Quit' 'Red' 'quit'))
    $fSel = 0
    Wipe
    while ($true) {
        Frame 'Red' 'FAILED' {
            Row
            BannerRow '  DEPLOYMENT FAILED  ' 'White' 'Red'
            Row
            Row (Pt '  Failed step : ' 'Gray') (Pt (Fit $s.Name 50) 'White')
            Row (Pt '  Reason      : ' 'Gray') (Pt (Fit $s.Detail 52) 'Red')
            Row
            Row (Pt '  Last log lines:' 'DarkGray')
            for ($i = 0; $i -lt 8; $i++) {
                $line = ''
                if ($i -lt $tail.Count) { $line = Fit $tail[$i] 66 }
                Row (Pt ('  ' + $G.LogBar + ' ' + $line) 'DarkGray')
            }
            Row
            SepLine
            ActionBar $fItems $fSel
            HintRow ($G.Left + '  ' + $G.Right + '  move          Enter  select')
        }
        $k = [Console]::ReadKey($true).Key
        $cmd = ''
        if ($k -eq 'LeftArrow' -or $k -eq 'UpArrow') { $fSel = ($fSel + $fItems.Count - 1) % $fItems.Count }
        elseif ($k -eq 'RightArrow' -or $k -eq 'DownArrow') { $fSel = ($fSel + 1) % $fItems.Count }
        elseif ($k -eq 'Enter') { $cmd = $fItems[$fSel].Id }
        if ($cmd -eq 'retry') { return 'retry' }
        if ($cmd -eq 'quit') { return 'quit' }
        if ($cmd -eq 'log') {
            if ($script:FailLog -and (Test-Path -LiteralPath $script:FailLog)) { Start-Process -FilePath 'notepad.exe' -ArgumentList ('"' + $script:FailLog + '"') }
            else { Start-Process -FilePath 'explorer.exe' -ArgumentList ('"' + $Logs + '"') }
        }
    }
}

function Show-Shutdown {
    $script:Theme = 'Magenta'; $script:Badge = 'SHUTDOWN'
    $items = @()
    [void](Resolve-PidFile @('mysql', 'vite', 'web', 'reverb', 'scheduler', 'queue'))   # also what an earlier run left behind
    $items += (Mk 'Stop application server' { Stop-TrackedName 'web'; Res 'ok' 'offline' })
    if ($UseReverb) { $items += (Mk 'Stop live chat (Reverb)' { Stop-TrackedName 'reverb'; Res 'ok' 'offline' }) }
    if ((Get-TrackedProc 'scheduler') -or (Get-TrackedProc 'queue')) { $items += (Mk 'Stop scheduler + workers' { Stop-TrackedName 'scheduler'; Stop-TrackedName 'queue'; Res 'ok' 'offline' }) }
    if (Get-TrackedProc 'vite') { $items += (Mk 'Stop Vite dev server' { Stop-TrackedName 'vite'; Res 'ok' 'offline' }) }
    if ($script:StartedMySQL) { $items += (Mk 'Stop MySQL database' { Stop-TrackedName 'mysql'; $script:StartedMySQL = $false; Res 'ok' 'offline' }) }
    if ($LanMode) { $items += (Mk 'Close firewall ports' { Remove-LanFirewall; Res 'ok' 'LSPDMS rule removed' }) }
    $script:Steps = $items; $script:pct = 0; $script:T0 = Get-Date; $script:Banner = $null
    Wipe
    Render-Steps $script:Theme $script:Badge
    foreach ($s in $items) {
        $s.State = 'run'; $s.Detail = 'stopping...'
        for ($i = 0; $i -lt 5; $i++) { Pump }
        $out = @(& $s.Action)
        $s.State = 'ok'; $s.Detail = 'offline'
        Pump
    }
    Stop-Tracked @()
    try { [void][Lsp.Pwr]::SetThreadExecutionState([uint32]2147483648) } catch {}
    Restore-Network
    Write-LaunchLog '=== shut down'
    $script:Banner = @{ T = '  SYSTEM OFFLINE - GOODBYE  '; F = 'White'; B = 'DarkMagenta' }
    for ($i = 0; $i -lt 14; $i++) { Pump }
    Start-Sleep -Milliseconds 800
}

# ----------------------------- administrator check -----------------------------
function Show-AdminRequired {
    $name = 'LSPDMS_Launcher.bat'
    if ($f) { $name = [IO.Path]::GetFileName($f) }
    Wipe
    Frame 'Red' 'ADMIN REQUIRED' {
        Row
        BannerRow '  ADMINISTRATOR PERMISSION NEEDED  ' 'White' 'Red'
        Row
        Row (Pt '  WARNING: LSPDMS will NOT run unless you open this file' 'Yellow')
        Row (Pt '  as Administrator.' 'Yellow')
        Row
        Row (Pt '  How to do it:' 'White')
        Row
        Row (Pt '    1. Close this window.' 'Gray')
        Row (Pt '    2. Right-click  ' 'Gray') (Pt $name 'Cyan')
        Row (Pt '    3. Click  ' 'Gray') (Pt 'Run as administrator' 'Green')
        Row (Pt '       (Windows 11: click "Show more options" first)' 'DarkGray')
        Row (Pt '    4. Click  ' 'Gray') (Pt 'Yes' 'Green') (Pt '  when Windows asks for permission.' 'Gray')
        Row
        SepLine
        Row (Pt '  Press any key to close' 'Gray')
    }
    while ([Console]::KeyAvailable) { [void][Console]::ReadKey($true) }
    [void][Console]::ReadKey($true)
}

# ------------------------ only one launcher at a time ------------------------
function Show-AlreadyRunning {
    Wipe
    Frame 'Yellow' 'ALREADY OPEN' {
        Row
        BannerRow '  LSPDMS IS ALREADY RUNNING  ' 'Black' 'Yellow'
        Row
        Row (Pt '  Another LSPDMS launcher window is already open.' 'White')
        Row (Pt '  Look for it on your taskbar (its title starts with LSPDMS).' 'Gray')
        Row
        Row (Pt '  To start a new one, shut the old one down first.' 'Gray')
        Row
        SepLine
        Row (Pt '  Press any key to close' 'Gray')
    }
    while ([Console]::KeyAvailable) { [void][Console]::ReadKey($true) }
    [void][Console]::ReadKey($true)
}

# --------------------------------- mode menu ---------------------------------
function Test-Internet {
    foreach ($h in '1.1.1.1', '8.8.8.8') {
        try {
            $p = New-Object Net.NetworkInformation.Ping
            if ($p.Send($h, 2000).Status -eq 'Success') { return $true }
        } catch {}
    }
    return $false
}

function Show-NoNetwork {
    Wipe
    Frame 'Red' 'NO NETWORK' {
        Row
        BannerRow '  NO INTERNET CONNECTION  ' 'White' 'Red'
        Row
        Row (Pt '  Please check your internet connection' 'White')
        Row (Pt '  (Wi-Fi, cable or hotspot) and try again.' 'Gray')
        Row
        Row (Pt '  Changed network? Choose Refresh network on the next screen.' 'DarkGray')
        Row (Pt '  Or choose Just this device.' 'DarkGray')
        Row
        SepLine
        Row (Pt '  Press any key to go back to the choice' 'Gray')
    }
    while ([Console]::KeyAvailable) { [void][Console]::ReadKey($true) }
    [void][Console]::ReadKey($true)
}

function Get-NetHint {
    # the PC's own Mobile hotspot shares the Wi-Fi radio and can stop it getting an address from the router
    if (Test-HotspotOn) { return 'Mobile hotspot is ON - turn it off if Wi-Fi has no address' }
    return ''
}

# re-scan for the current network; if none: renew the IP, then restart the Wi-Fi adapter (needs Administrator)
function Refresh-Network {
    $script:Quiet = $true
    $hint = Get-NetHint
    $ip = $null
    # 1) plain re-scan (enough when you just joined a different network)
    $t = Get-Date
    while (((Get-Date) - $t).TotalSeconds -lt 3) {
        $ip = Get-LanIp
        if ($ip) { break }
        Pump
    }
    if ($ip) { return "Network found: $ip" }
    # 2) nothing found: fixing it needs Administrator
    if (-not (Test-Admin)) {
        if ($f) {
            try {
                Start-Process -FilePath $env:ComSpec -ArgumentList "/c `"`"$f`"`"" -Verb RunAs -ErrorAction Stop
                exit
            } catch { return 'Fixing the network needs Administrator (cancelled)' }
        }
        return 'Fixing the network needs Administrator'
    }
    $ad = Get-WorkAdapter
    if (-not $ad) { return 'No active adapter - connect to Wi-Fi or cable first' }
    # 3) release + renew the IP
    $p = Start-Hidden 'cmd.exe' ('/c ipconfig /release "' + $ad.Name + '"') "$Logs\ip-release.log"; Wait-Proc $p 15
    $p = Start-Hidden 'cmd.exe' ('/c ipconfig /renew "' + $ad.Name + '"') "$Logs\ip-renew.log"; Wait-Proc $p 25
    $t = Get-Date
    while (((Get-Date) - $t).TotalSeconds -lt 8) {
        $ip = Get-LanIp
        if ($ip) { return "Network found: $ip (renewed)" }
        Pump
    }
    # 4) still nothing: restart the Wi-Fi adapter (re-joins the router / re-picks the 2.4 or 5 GHz band), then renew again
    $p = Start-Hidden 'powershell.exe' ('-NoProfile -Command Restart-NetAdapter -Name "' + $ad.Name + '" -Confirm:$false') "$Logs\ip-restart.log"; Wait-Proc $p 40
    $t = Get-Date; $renewed = $false
    while (((Get-Date) - $t).TotalSeconds -lt 25) {
        $ip = Get-LanIp
        if ($ip) { return "Network found: $ip (Wi-Fi restarted)" }
        if (-not $renewed -and ((Get-Date) - $t).TotalSeconds -gt 10) {
            $renewed = $true
            $p = Start-Hidden 'cmd.exe' ('/c ipconfig /renew "' + $ad.Name + '"') "$Logs\ip-renew2.log"; Wait-Proc $p 15
        }
        Pump
    }
    if ($hint) { return $hint }
    return 'Router gave no address - reconnect Wi-Fi, then Refresh again'
}

# ------------------------- program / dependency check -------------------------
function Dep($name, $state, $detail, $fix = '') {
    [pscustomobject]@{ Name = $name; State = $state; Detail = [string]$detail; Fix = $fix }
}

function Get-Deps {
    $d = @()
    # PHP (Laragon)
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $v = ''
        try { $v = [string](& php -r 'echo PHP_VERSION;' 2>$null) } catch {}
        $d += (Dep 'PHP' 'ok' ('v' + $v + '  ' + $phpCmd.Source))
        $mods = @()
        try { $mods = @(& php -m 2>$null | ForEach-Object { ([string]$_).Trim().ToLower() }) } catch {}
        $need = @('mbstring', 'openssl', 'pdo_mysql', 'fileinfo', 'curl', 'tokenizer', 'xml', 'ctype', 'json')
        $miss = @($need | Where-Object { $mods -notcontains $_ })
        if ($mods.Count -eq 0) { $d += (Dep 'PHP extensions' 'warn' 'could not read the PHP module list') }
        elseif ($miss.Count -gt 0) { $d += (Dep 'PHP extensions' 'warn' ('enable in php.ini: ' + ($miss -join ', ')) 'phpext') }
        else { $d += (Dep 'PHP extensions' 'ok' 'all required ones present') }
    } else {
        $d += (Dep 'PHP' 'fail' 'not found (install Laragon)' 'laragon')
    }
    # Composer (only needed to install Laravel packages)
    $cc = Get-ComposerCmd
    $vendorOk = Test-Path "$Project\vendor\autoload.php"
    if ($cc) { $d += (Dep 'Composer' 'ok' 'found') }
    elseif ($vendorOk) { $d += (Dep 'Composer' 'ok' 'not found (not needed: packages present)') }
    else { $d += (Dep 'Composer' 'fail' 'not found' 'composer') }
    # Node.js + npm (Vite builds the front-end)
    $n = Get-Command node -ErrorAction SilentlyContinue
    if ($n) {
        $nv = ''
        try { $nv = [string](& node -v 2>$null) } catch {}
        $d += (Dep 'Node.js' 'ok' ($nv + '  ' + $n.Source))
    } else { $d += (Dep 'Node.js' 'fail' 'not found' 'node') }
    $np = Get-Command npm -ErrorAction SilentlyContinue
    if ($np) { $d += (Dep 'npm' 'ok' $np.Source) } else { $d += (Dep 'npm' 'fail' 'not found (comes with Node.js)' 'node') }
    # MySQL (Laragon, or already running)
    if ($UseMySQL) {
        if (Test-Port $MySQLPort) { $d += (Dep 'MySQL database' 'ok' "running on :$MySQLPort") }
        else {
            $m = Find-MySqld
            if ($m) { $d += (Dep 'MySQL database' 'ok' 'installed - starts automatically') }
            else { $d += (Dep 'MySQL database' 'warn' 'not found (install Laragon)' 'laragon') }
        }
        # backups before new migrations need the dump tool
        $dump = Find-MysqlDump
        if ($dump) { $d += (Dep 'Backup tool (mysqldump)' 'ok' $dump) }
        elseif ($BackupBeforeMigrate) { $d += (Dep 'Backup tool (mysqldump)' 'warn' 'not found - new migrations will wait') }
    }
    # OPcache makes every page ~10x faster on PHP's built-in server (switched on per launch, php.ini untouched)
    if ($phpCmd -and $UseOpcache) {
        if (Get-OpcacheArgs) { $d += (Dep 'PHP OPcache' 'ok' 'available - used by the web server') }
        else { $d += (Dep 'PHP OPcache' 'warn' 'not available in this PHP (pages slower)') }
    }
    # the project itself
    if (Test-Path "$Project\artisan") {
        $d += (Dep 'Source code' 'ok' $Project)
        if ($vendorOk) { $d += (Dep 'Laravel packages' 'ok' 'vendor folder present') }
        else {
            $vd = 'vendor missing (composer install)'
            if ($script:VendorErr) { $vd = $script:VendorErr }
            $d += (Dep 'Laravel packages' 'fail' $vd 'vendor')
        }
        if (Test-Path "$Project\node_modules\.bin\vite.cmd") { $d += (Dep 'Vite + JS packages' 'ok' 'Vite ready') }
        elseif (Test-Path "$Project\node_modules") { $d += (Dep 'Vite + JS packages' 'warn' 'node_modules found, Vite missing' 'nodemods') }
        else { $d += (Dep 'Vite + JS packages' 'fail' 'not installed (npm install)' 'nodemods') }
        $envf = "$Project\.env"
        $txt = ''
        if (Test-Path $envf) { $txt = [string](Get-Content $envf -Raw) }
        if (-not (Test-Path $envf)) { $d += (Dep 'App config (.env)' 'fail' '.env missing' 'env') }
        elseif ($txt -notmatch '(?m)^APP_KEY=\S+') { $d += (Dep 'App config (.env)' 'fail' 'APP_KEY is empty' 'env') }
        else {
            $d += (Dep 'App config (.env)' 'ok' '.env ready')
            if ($CheckDatabase -and $phpCmd) { $d += (Test-DbDep) }
        }
    } else {
        $d += (Dep 'Source code' 'fail' 'not on this device - can download from GitHub' 'source')
    }
    $d
}

function Test-ToolKey($k) {
    if ($k -eq 'laragon' -or $k -eq 'php') { return [bool](Get-Command php -ErrorAction SilentlyContinue) }
    if ($k -eq 'node') { return [bool](Get-Command node -ErrorAction SilentlyContinue) }
    if ($k -eq 'composer') { return [bool](Get-ComposerCmd) }
    return $false
}

# downloads composer.phar straight from getcomposer.org and checks that it runs (needs PHP only)
function Install-ComposerPhar {
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { return (Res 'fail' 'PHP not found - Composer needs PHP') }
    $log = "$Logs\install-composer.log"
    try { New-Item -ItemType Directory -Force $ToolsDir | Out-Null } catch {}
    $phar = Join-Path $ToolsDir 'composer.phar'
    $tmp  = $phar + '.tmp'
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    $j = Start-Job -ScriptBlock {
        param($u, $o)
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            $ProgressPreference = 'SilentlyContinue'
            Invoke-WebRequest -UseBasicParsing -Uri $u -OutFile $o -TimeoutSec 180
            'OK'
        } catch { 'ERR: ' + $_.Exception.Message }
    } -ArgumentList $ComposerPharUrl, $tmp
    while ($j.State -eq 'Running') { Pump }
    $o = [string](@(Receive-Job $j)[-1])
    Remove-Job $j -Force
    if ($o -ne 'OK' -or -not (Test-Path $tmp)) {
        Set-Content -Path $log -Value ('composer.phar download failed: ' + $o) -ErrorAction SilentlyContinue
        $script:FailLog = $log
        return (Res 'fail' 'could not download composer.phar - check internet')
    }
    Move-Item $tmp $phar -Force
    $chk = "$Logs\composer-check.log"
    $p = Start-Hidden 'php' ('"' + $phar + '" --version --no-ansi') $chk
    Wait-Proc $p 60
    $v = [string](Get-Content $chk -Raw)
    if ($v -match 'Composer version') { return (Res 'ok' 'installed (composer.phar)') }
    Remove-Item $phar -Force -ErrorAction SilentlyContinue
    Set-Content -Path $log -Value ("composer.phar would not run:`n" + $v + "`n" + [string](Get-Content "$chk.err" -Raw)) -ErrorAction SilentlyContinue
    $script:FailLog = $log
    Res 'fail' 'composer.phar will not run - check PHP extensions'
}

function Install-Tool($key) {
    if (Test-ToolKey $key) { return (Res 'ok' 'already present') }
    if ($key -eq 'composer') {
        $cr = Install-ComposerPhar
        if ($cr.State -eq 'ok') { return $cr }
        # otherwise fall through to winget / the download page below
    }
    $id = $WingetIds[$key]
    $url = $DownloadUrls[$key]
    if ($id -and (Get-Command winget -ErrorAction SilentlyContinue)) {
        $p = Start-Hidden 'cmd.exe' ('/c winget install --id ' + $id + ' -e --silent --accept-package-agreements --accept-source-agreements') "$Logs\install-$key.log"
        Wait-Proc $p 900
        Sync-Path
        Add-ToolPaths
        if (Test-ToolKey $key) { return (Res 'ok' 'installed') }
    }
    if ($url) { Start-Process $url }
    Res 'warn' 'download page opened - install, then Re-check'
}

# turns on the PHP extensions Laravel / Composer need by editing php.ini (a backup php.ini.lspdms.bak is kept).
# Fixes the usual causes of "composer install failed": no php.ini, extension_dir pointing nowhere, extensions commented out.
# If php.ini cannot be written, a private copy is used through the PHPRC variable instead. Returns the extensions it enabled.
function Enable-PhpExtensions($extra = @()) {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCmd) { return @() }
    $phpDir = Split-Path -Parent $phpCmd.Source
    $extDir = Join-Path $phpDir 'ext'
    if (-not (Test-Path $extDir)) { return @() }
    # which php.ini does this PHP use? create one if there is none
    $ini = ''
    try { $ini = ([string](& php -r 'echo php_ini_loaded_file();' 2>$null)).Trim() } catch {}
    if (-not $ini -or -not (Test-Path $ini)) {
        $ini = Join-Path $phpDir 'php.ini'
        if (-not (Test-Path $ini)) {
            $made = $false
            foreach ($t in 'php.ini-development', 'php.ini-production') {
                if (Test-Path (Join-Path $phpDir $t)) { try { Copy-Item (Join-Path $phpDir $t) $ini -ErrorAction Stop; $made = $true } catch {}; if ($made) { break } }
            }
            if (-not $made) { try { Set-Content -Path $ini -Value '; created by the LSPDMS launcher' -Encoding ASCII -ErrorAction Stop } catch {} }
        }
    }
    $txt = ''
    if (Test-Path $ini) { try { $txt = [IO.File]::ReadAllText($ini) } catch { $txt = '' } }
    $loaded = @()
    try { $loaded = @(& php -m 2>$null | ForEach-Object { ([string]$_).Trim().ToLower() }) } catch {}
    $want = @('openssl', 'curl', 'mbstring', 'fileinfo', 'zip', 'gd', 'intl', 'pdo_mysql', 'pdo_sqlite', 'sqlite3')
    foreach ($e in $extra) { $e = ([string]$e).ToLower(); if ($e -and $want -notcontains $e) { $want += $e } }
    $changed = $false
    $done = @()
    # extension_dir must point at the real ext folder, otherwise every extension below is silently ignored
    $curDir = ''
    try { $curDir = ([string](& php -r 'echo ini_get(''extension_dir'');' 2>$null)).Trim() } catch {}
    if ($curDir -and -not [IO.Path]::IsPathRooted($curDir)) { $curDir = Join-Path $phpDir $curDir }
    if (-not $curDir -or -not (Test-Path (Join-Path $curDir 'php_openssl.dll'))) {
        $dirLine = ('extension_dir = "' + $extDir + '"').Replace('$', '$$')
        $activeDir = '(?m)^[ \t]*extension_dir[ \t]*=.*$'
        if ($txt -match $activeDir) { $txt = [regex]::Replace($txt, $activeDir, $dirLine) }
        else { $txt = $txt.TrimEnd() + "`r`n" + $dirLine.Replace('$$', '$') + "`r`n" }
        $changed = $true
    }
    foreach ($e in $want) {
        if ($loaded -contains $e) { continue }
        if (-not (Test-Path (Join-Path $extDir ('php_' + $e + '.dll')))) { continue }
        $esc = [regex]::Escape($e)
        $active = '(?m)^[ \t]*extension[ \t]*=[ \t]*"?(?:php_)?' + $esc + '(?:\.dll)?"?[ \t]*\r?$'
        $off    = '(?m)^[ \t]*;[ \t]*(extension[ \t]*=[ \t]*"?(?:php_)?' + $esc + '(?:\.dll)?"?)[ \t]*(\r?)$'
        if ($txt -match $active) { continue }
        if ($txt -match $off) { $txt = [regex]::Replace($txt, $off, '$1$2') }
        else { $txt = $txt.TrimEnd() + "`r`nextension=" + $e + "`r`n" }
        $done += $e; $changed = $true
    }
    if ($changed) {
        $saved = $false
        try {
            if ((Test-Path $ini) -and -not (Test-Path ($ini + '.lspdms.bak'))) { Copy-Item $ini ($ini + '.lspdms.bak') -ErrorAction SilentlyContinue }
            [IO.File]::WriteAllText($ini, $txt, (New-Object Text.UTF8Encoding($false)))
            $saved = $true
        } catch {}
        if (-not $saved) {
            # php.ini is read-only / protected: keep a private copy and point PHP at it for this launcher session
            try {
                $privDir = Join-Path $ToolsDir 'php'
                New-Item -ItemType Directory -Force $privDir | Out-Null
                [IO.File]::WriteAllText((Join-Path $privDir 'php.ini'), $txt, (New-Object Text.UTF8Encoding($false)))
                $env:PHPRC = $privDir
            } catch { return @() }
        }
        # keep only what PHP really loads now
        $now = @()
        try { $now = @(& php -m 2>$null | ForEach-Object { ([string]$_).Trim().ToLower() }) } catch {}
        $done = @($done | Where-Object { $now -contains $_ })
    }
    return $done
}

# "Install missing" step: makes sure the PHP extensions Laravel needs are active
function Install-PhpExtensions {
    $fixed = @(Enable-PhpExtensions)
    $mods = @()
    try { $mods = @(& php -m 2>$null | ForEach-Object { ([string]$_).Trim().ToLower() }) } catch {}
    $miss = @(@('mbstring', 'openssl', 'pdo_mysql', 'fileinfo', 'curl') | Where-Object { $mods -notcontains $_ })
    if ($miss.Count -eq 0) {
        if ($fixed.Count -gt 0) { return (Res 'ok' ('enabled: ' + ($fixed -join ', '))) }
        return (Res 'ok' 'all required ones present')
    }
    Res 'fail' ('still missing: ' + ($miss -join ', ') + ' - this PHP has no DLL for them; pick a full PHP in Laragon')
}

function Install-Vendor($force = $false) {
    $script:VendorErr = ''
    if (-not $force -and (Test-Path "$Project\vendor\autoload.php")) { return (Res 'ok' 'already installed') }
    $cc = Get-ComposerCmd
    if (-not $cc) { $script:VendorErr = 'Composer not found - install it first'; return (Res 'fail' 'Composer not found - install it first') }
    $env:COMPOSER_ALLOW_SUPERUSER = '1'; $env:COMPOSER_NO_INTERACTION = '1'; $env:COMPOSER_PROCESS_TIMEOUT = '1500'
    [void](Enable-PhpExtensions)
    $log = "$Logs\composer.log"
    $txt = ''; $miss = @()
    for ($try = 1; $try -le 3; $try++) {
        Remove-Item $log, "$log.err" -Force -ErrorAction SilentlyContinue
        $p = Start-Hidden 'cmd.exe' ('/c ' + $cc + ' install --no-interaction --prefer-dist') $log
        Wait-Proc $p 1500
        # success = exit code 0. An old vendor\ left behind by a failed install does not count
        if ($p.ExitCode -eq 0 -and (Test-Path "$Project\vendor\autoload.php")) { return (Res 'ok' 'packages installed') }
        $txt =(@(Get-LogTail $log 200)) -join "`n"
        $miss = @([regex]::Matches($txt, 'ext-([a-z0-9_]+)') | ForEach-Object { $_.Groups[1].Value } | Select-Object -Unique)
        if ($txt -match 'your php version') { break }     # a newer PHP is needed: retrying cannot fix that
        if ($miss.Count -gt 0) {
            $fixed = @(Enable-PhpExtensions $miss)         # turn on what Composer says is missing, then try again
            if ($fixed.Count -eq 0) { break }
        }
    }
    if ($txt -match 'your php version') {
        $pv = ''
        try { $pv = [string](& php -r 'echo PHP_VERSION;' 2>$null) } catch {}
        $why = 'project needs a newer PHP (you have v' + $pv + ')'
    }
    elseif ($miss.Count -gt 0) { $why = 'PHP extension missing: ' + ($miss -join ', ') }
    elseif ($txt -match 'Could not resolve host|curl error|timed out|Failed to download') { $why = 'network error - check internet, retry' }
    else { $why = 'composer install failed - see composer.log' }
    $script:VendorErr = $why
    $script:FailLog = $log
    Res 'fail' $why
}

function Install-NodeMods($force = $false) {
    if (-not $force -and (Test-Path "$Project\node_modules\.bin\vite.cmd")) { return (Res 'ok' 'already installed') }
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { return (Res 'fail' 'npm not found - install Node.js first') }
    $p = Start-Hidden 'cmd.exe' '/c npm install --no-audit --no-fund' "$Logs\npm-install.log"
    Wait-Proc $p 1500
    if ($p.ExitCode -eq 0 -and (Test-Path "$Project\node_modules\.bin\vite.cmd")) { return (Res 'ok' 'packages installed') }
    $script:FailLog = "$Logs\npm-install.log"
    if ($p.ExitCode -ne 0 -and (Test-Path "$Project\node_modules\.bin\vite.cmd")) { return (Res 'warn' 'npm install failed - old packages kept') }
    if (Test-Path "$Project\node_modules") { return (Res 'warn' 'installed, but Vite not found') }
    Res 'fail' 'npm install failed - see log'
}

function Install-Env {
    $envf = "$Project\.env"
    $restored = Restore-EnvKeep
    if (-not (Test-Path $envf)) {
        if (Test-Path "$Project\.env.example") { Copy-Item "$Project\.env.example" $envf }
        else { return (Res 'fail' '.env.example not found') }
    }
    [void](Fix-EnvDatabase)
    $txt = [string](Get-Content $envf -Raw)
    if ($txt -notmatch '(?m)^APP_KEY=\S+' -and (Test-Path "$Project\vendor\autoload.php")) {
        $p = Start-Hidden 'php' 'artisan key:generate --force' "$Logs\key.log"
        Wait-Proc $p 60
        $txt = [string](Get-Content $envf -Raw)
    }
    if ($txt -notmatch '(?m)^APP_KEY=\S+') {
        # artisan needs vendor/ (Composer). Laravel's key is just 32 random bytes, so make it directly
        $bytes = New-Object byte[] 32
        $rng = [Security.Cryptography.RandomNumberGenerator]::Create(); $rng.GetBytes($bytes); $rng.Dispose()
        $key = 'base64:' + [Convert]::ToBase64String($bytes)
        if ($txt -match '(?m)^APP_KEY=') { $txt = [regex]::Replace($txt, '(?m)^APP_KEY=[^\r\n]*', 'APP_KEY=' + $key) }
        else { $txt = $txt.TrimEnd() + "`r`nAPP_KEY=$key`r`n" }
        [IO.File]::WriteAllText($envf, $txt, (New-Object Text.UTF8Encoding($false)))
        $txt = [string](Get-Content $envf -Raw)
    }
    if ($txt -match '(?m)^APP_KEY=\S+') {
        Save-EnvKeep
        if ($restored) { return (Res 'ok' 'restored from the previous install') }
        return (Res 'ok' 'ready - check DB settings in .env')
    }
    Res 'fail' 'could not create the app key'
}

function Get-Sha256($t) {
    $h = [Security.Cryptography.SHA256]::Create()
    $b = $h.ComputeHash([Text.Encoding]::UTF8.GetBytes([string]$t))
    (($b | ForEach-Object { $_.ToString('x2') }) -join '')
}

# the .env login was refused: root is used only to (re)create LSPDMS's own account and reset its password; the new
# password is saved in .env. root itself is never written to .env. $null = root could not log in either (see dbsec.log)
function Repair-DbLogin($h, $p) {
    if (-not (Test-Path -LiteralPath (Join-Path $Project '.env')) -or -not (Test-LocalDbHost $h)) { return $null }
    $a = Ensure-AppDbAccount $h $p
    if ($a.State -eq 'ok') { return ('fixed .env login (own account ' + $a.User + ')') }   # never show the password itself
    return $null
}

# connects to MySQL with the login from .env (the same one Laravel will use) - just a check, nothing is changed
function Test-DbDep {
    $conn = (Get-EnvValue 'DB_CONNECTION').ToLower()
    if ($conn -ne 'mysql' -and $conn -ne 'mariadb') {
        $what = $conn; if (-not $what) { $what = 'not MySQL' }
        return (Dep 'Database connection' 'ok' ('not using MySQL (' + $what + ')'))
    }
    $dbh = Get-EnvValue 'DB_HOST'; if (-not $dbh) { $dbh = '127.0.0.1' }
    $dbp = Get-EnvValue 'DB_PORT'; if (-not $dbp) { $dbp = '3306' }
    $name = Get-EnvValue 'DB_DATABASE'
    $local = ($dbh -eq '127.0.0.1' -or $dbh -eq 'localhost' -or $dbh -eq '::1')
    if ($local -and -not (Test-Port $dbp)) {
        if (Find-MySqld) { return (Dep 'Database connection' 'ok' 'MySQL not running yet - starts with the launcher') }
        return (Dep 'Database connection' 'warn' 'cannot test - MySQL is not installed/running')
    }
    $env:LSP_H = $dbh; $env:LSP_P = $dbp; $env:LSP_U = (Get-EnvValue 'DB_USERNAME'); $env:LSP_W = (Get-EnvValue 'DB_PASSWORD'); $env:LSP_D = $name
    $code = @(
        '<?php',
        '$h = getenv("LSP_H"); $p = getenv("LSP_P"); $u = getenv("LSP_U"); $w = getenv("LSP_W"); $d = getenv("LSP_D");',
        'try {',
        '  $pdo = new PDO("mysql:host=$h;port=$p", $u, $w, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);',
        '  $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?"); $s->execute([$d]);',
        '  echo ($s->fetchColumn() > 0) ? "OK" : "NODB";',
        '} catch (Throwable $e) { echo "ERR: " . $e->getMessage(); exit(1); }'
    ) -join "`n"
    $php = "$Logs\dbcheck.php"
    Set-Content -Path $php -Value $code -Encoding ASCII
    $prevQ = $script:Quiet
    $script:Quiet = $true
    $out = ''
    try {
        $p = Start-Hidden 'php' ('"' + $php + '"') "$Logs\dbcheck.log"
        Wait-Proc $p 20
        $out = ([string](Get-Content "$Logs\dbcheck.log" -Raw)).Trim()
    } catch { $out = 'ERR: ' + $_.Exception.Message }
    $script:Quiet = $prevQ
    foreach ($n in 'LSP_H', 'LSP_P', 'LSP_U', 'LSP_W', 'LSP_D') { Remove-Item "Env:\$n" -ErrorAction SilentlyContinue }
    if ($out -eq 'OK') { return (Dep 'Database connection' 'ok' ("connected - database '" + $name + "' exists")) }
    if ($out -eq 'NODB') { return (Dep 'Database connection' 'ok' ("connected - '" + $name + "' will be created on first run")) }
    if ($out -match '1045') {
        $fixedNote = Repair-DbLogin $dbh $dbp
        if ($fixedNote) { return (Dep 'Database connection' 'ok' $fixedNote) }
        return (Dep 'Database connection' 'fail' ('login refused, root too - see dbsec.log'))
    }
    if ($out -match '2002|2003|refused') { return (Dep 'Database connection' 'fail' ('MySQL not reachable at ' + $dbh + ':' + $dbp)) }
    if ($out -match '^ERR') { return (Dep 'Database connection' 'fail' ($out -replace '^ERR:\s*(SQLSTATE\[\w+\]\s*)?', '')) }
    Dep 'Database connection' 'warn' 'could not run the check'
}

# asks for the download password (typed as ****). Returns $true only when it is correct
function Read-SourcePassword {
    if (-not $SourcePasswordHash) { return $true }
    $pw = ''; $msg = ''; $tries = 0
    Wipe
    while ($true) {
        Frame 'Yellow' 'PASSWORD' {
            Row
            Row (Pt '  Download source code' 'White')
            Row (Pt '  Enter the password to download LSPDMS from GitHub.' 'Gray')
            Row
            Row (Pt '  Password  ' 'Gray') (Pt (('*' * $pw.Length) + '_') 'Cyan') (Pt (' ' * (40 - $pw.Length)) 'Gray')
            Row
            Row (Pt ('  ' + $msg.PadRight(60)) 'Red')
            Row
            HintRow 'Enter  confirm          Esc  cancel'
        }
        if ($tries -ge 3) { Start-Sleep -Milliseconds 1800; return $false }
        $ki = [Console]::ReadKey($true)
        if ($ki.Key -eq 'Escape') { return $false }
        elseif ($ki.Key -eq 'Enter') {
            if ((Get-Sha256 $pw) -eq $SourcePasswordHash) { return $true }
            $tries++
            $pw = ''
            if ($tries -ge 3) { $msg = 'Too many wrong attempts - download cancelled' }
            else { $msg = 'Wrong password (' + $tries + ' of 3)' }
        }
        elseif ($ki.Key -eq 'Backspace') { if ($pw.Length -gt 0) { $pw = $pw.Substring(0, $pw.Length - 1) } }
        elseif ($ki.KeyChar -and -not [char]::IsControl($ki.KeyChar) -and $pw.Length -lt 38) { $pw += [string]$ki.KeyChar }
    }
}

# ---------------------------- GitHub: version + download ----------------------------
function Get-RepoParts {
    $m = [regex]::Match($RepoUrl, '^https://github\.com/([^/]+)/([^/]+?)(\.git)?/?$')
    if ($m.Success) { return @{ Owner = $m.Groups[1].Value; Name = $m.Groups[2].Value } }
    $null
}

# the commit id of the latest push on GitHub ('' when GitHub cannot be reached)
function Get-RemoteSha {
    $sha = ''
    $ref = 'HEAD'
    if ($RepoBranch) { $ref = 'refs/heads/' + $RepoBranch }
    $git = Get-Command git -ErrorAction SilentlyContinue
    if ($git) {
        $log = "$Logs\lsremote.log"
        Remove-Item $log, "$log.err" -Force -ErrorAction SilentlyContinue
        $env:GIT_TERMINAL_PROMPT = '0'
        try {
            $p = Start-Hidden $git.Source ('ls-remote "' + $RepoUrl + '" ' + $ref) $log
            Wait-Proc $p 25
        } catch {}
        Remove-Item Env:\GIT_TERMINAL_PROMPT -ErrorAction SilentlyContinue
        if (Test-Path $log) {
            $line = Get-Content $log -ErrorAction SilentlyContinue | Where-Object { $_ -match '^[0-9a-f]{40}\s' } | Select-Object -First 1
            if ($line -match '^([0-9a-f]{40})') { $sha = $Matches[1] }
        }
    }
    if (-not $sha) {
        $m = Get-RepoParts
        if ($m) {
            $apiRef = 'HEAD'
            if ($RepoBranch) { $apiRef = $RepoBranch }
            try {
                [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
                $r = Invoke-RestMethod -Uri ('https://api.github.com/repos/' + $m.Owner + '/' + $m.Name + '/commits/' + $apiRef) -Headers @{ 'User-Agent' = 'LSPDMS-Launcher'; 'Accept' = 'application/vnd.github+json' } -TimeoutSec 15
                if ([string]$r.sha -match '^[0-9a-f]{40}$') { $sha = [string]$r.sha }
            } catch {}
        }
    }
    $sha
}

# the commit id this PC has ('' = unknown): the saved version file first, else the project's own .git folder
function Get-LocalSha {
    # a git working copy (your dev PC): the real commit is git's HEAD. The version file is only the launcher's own note for PCs that have no .git
    $git = Get-Command git -ErrorAction SilentlyContinue
    if ($git -and (Test-Path "$Project\.git")) {
        try {
            $v = ([string](& $git.Source -C $Project rev-parse HEAD 2>$null)).Trim()
            if ($v -match '^[0-9a-f]{40}$') { return $v }
        } catch {}
    }
    $vf = Join-Path $Project $VersionFile
    if (Test-Path $vf) {
        $v = ([string](Get-Content $vf -ErrorAction SilentlyContinue | Select-Object -First 1)).Trim()
        if ($v -match '^[0-9a-f]{40}$') { return $v }
    }
    ''
}
function Save-Version($sha) {
    if ($sha) { try { Set-Content -Path (Join-Path $Project $VersionFile) -Value $sha -Encoding ASCII -Force } catch {} }
}
function Short-Sha($s) { if ($s) { ([string]$s).Substring(0, 7) } else { 'unknown' } }
function Clear-SourceTemp {
    foreach ($x in "$Logs\src-tmp", "$Logs\src-tmp-zip", "$Logs\source.zip") { Remove-Item $x -Recurse -Force -ErrorAction SilentlyContinue }
}

# downloads the latest code from GitHub into a temp folder ($script:SrcDir) and notes its commit id ($script:SrcSha).
# Uses git clone, or the ZIP when Git is not installed. Needs a progress screen (Prog-Begin) to be open.
function Get-SourceFiles {
    $tmp = "$Logs\src-tmp"; $zip = "$Logs\source.zip"; $log = "$Logs\source.log"
    Clear-SourceTemp
    $script:SrcDir = $null; $script:SrcSha = ''
    $got = $false
    Prog-Stage 'Retrieving file from GitHub...' 0 55
    $git = Get-Command git -ErrorAction SilentlyContinue
    if ($git) {
        $env:GIT_TERMINAL_PROMPT = '0'   # never wait on a hidden text prompt
        $p = Start-Hidden $git.Source ('clone --depth 1 ' + $(if ($RepoBranch) { '--branch ' + $RepoBranch + ' ' } else { '' }) + '"' + $RepoUrl + '" "' + $tmp + '"') $log
        Wait-Proc $p 900
        Remove-Item Env:\GIT_TERMINAL_PROMPT -ErrorAction SilentlyContinue
        $got = (Test-Path "$tmp\artisan")
        if ($got) {
            try {
                $s = ([string](& $git.Source -C $tmp rev-parse HEAD 2>$null)).Trim()
                if ($s -match '^[0-9a-f]{40}$') { $script:SrcSha = $s }
            } catch {}
        }
    }
    if (-not $got) {
        $m = Get-RepoParts
        if ($m) {
            Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
            Prog-Stage 'Retrieving file from GitHub (ZIP)...' 5 50
            $zipUrl = 'https://github.com/' + $m.Owner + '/' + $m.Name + $(if ($RepoBranch) { '/archive/refs/heads/' + $RepoBranch + '.zip' } else { '/archive/HEAD.zip' })
            $cmd = '-NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $ProgressPreference = ''SilentlyContinue''; Invoke-WebRequest -UseBasicParsing -Uri ''' + $zipUrl + ''' -OutFile ''' + $zip + '''"'
            $p = Start-Hidden 'powershell.exe' $cmd $log
            Wait-Proc $p 900
            if (Test-Path $zip) {
                Prog-Stage 'Extracting the files...' 55 68
                $cmd2 = '-NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -LiteralPath ''' + $zip + ''' -DestinationPath ''' + $tmp + '-zip'' -Force"'
                $p = Start-Hidden 'powershell.exe' $cmd2 $log
                Wait-Proc $p 600
                $inner = Get-ChildItem "$tmp-zip" -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
                if ($inner) { Move-Item $inner.FullName $tmp -ErrorAction SilentlyContinue }
                $got = (Test-Path "$tmp\artisan")
            }
        }
    }
    if (-not $got) { $script:FailLog = $log; return $false }
    Prog-Stage 'Verifying the download...' 70
    if (-not $script:SrcSha) { $script:SrcSha = Get-RemoteSha }
    $script:SrcDir = $tmp
    return $true
}

# downloads the project from GitHub and copies it into $Project (first install).
# Existing files are never overwritten (your .env, uploads, etc. stay as they are).
function Install-Source {
    if (Test-Path "$Project\artisan") { return (Res 'ok' 'already present') }
    if ($RepoUrl -notmatch '^https://') { return (Res 'fail' 'set $RepoUrl in the CONFIG section first') }
    if (-not (Test-InWww $Project)) { return (Res 'fail' ('target is not inside ' + (Get-WwwRoot))) }
    $res = $null
    try {
        Prog-Begin 'Yellow' 'DOWNLOADING' 'Downloading LSPDMS from GitHub'
        if (-not (Get-SourceFiles)) {
            $res = Res 'fail' 'download failed - check $RepoUrl, branch, internet, repo access'
        } else {
            $src = $script:SrcDir
            Prog-Stage ('Creating folder ' + $Project) 80
            New-Item -ItemType Directory -Force $Project | Out-Null
            Prog-Stage 'Copying the files into the project folder...' 85 97
            $p = Start-Hidden 'robocopy.exe' ('"' + $src + '" "' + $Project + '" /E /XC /XN /XO /XD "' + $src + '\.git" /NFL /NDL /NJH /NJS /NP') "$Logs\robocopy.log"
            Wait-Proc $p 300
            if (Test-Path "$Project\artisan") {
                Save-Version $script:SrcSha
                Set-LauncherPaths   # logs + state now live in the downloaded project
                Prog-Stage 'Completed.' 100
                Prog-Hold 12
                $res = Res 'ok' 'source code downloaded'
            } else {
                $script:FailLog = "$Logs\robocopy.log"
                $res = Res 'fail' 'could not copy the files into the project folder'
            }
        }
    } catch { $res = Res 'fail' $_.Exception.Message }
    Clear-SourceTemp
    Prog-End
    $res
}

# compares this PC with the latest push on GitHub. Returns State: current | outdated | offline
# quick "is GitHub reachable?" test (about 2 s at most), so an offline PC is not held up by the slower git/API lookups
function Test-GitHubReachable {
    $c = New-Object Net.Sockets.TcpClient
    try {
        $t = $c.ConnectAsync('github.com', 443)
        return ($t.Wait(2000) -and $c.Connected)
    } catch { return $false } finally { $c.Dispose() }
}

# GitHub cannot be reached: ask what to do. Returns 'retry', 'continue' (start with the version on this PC) or 'quit'
function Show-NoInternet($why, $note = '') {
    $items = @((BarItem 'R' 'Retry' 'Cyan' 'retry'), (BarItem 'C' 'Continue offline' 'Yellow' 'continue'), (BarItem 'Q' 'Quit' 'Red' 'quit'))
    $sel = 0
    Wipe
    while ($true) {
        Frame 'Yellow' 'OFFLINE' {
            Row
            BannerRow '  NO INTERNET CONNECTION  ' 'Black' 'Yellow'
            Row
            Row (Pt '  Cannot proceed with the update check: GitHub cannot be reached.' 'White')
            Row (Pt ('  ' + (Fit $why 68)) 'DarkGray')
            if ($note) { Row (Pt ('  ' + (Fit $note 68)) 'Yellow') }
            Row
            Row (Pt '  Retry             ' 'Gray') (Pt 'try connecting to GitHub again' 'DarkGray')
            Row (Pt '  Continue offline  ' 'Gray') (Pt 'start LSPDMS with the version on this PC' 'DarkGray')
            Row (Pt '                    ' 'Gray') (Pt '(team mode still works on the local network)' 'DarkGray')
            Row
            SepLine
            ActionBar $items $sel
            HintRow ($G.Left + '  ' + $G.Right + '  move          Enter  select')
        }
        $k = [Console]::ReadKey($true).Key
        $cmd = ''
        if ($k -eq 'LeftArrow' -or $k -eq 'UpArrow') { $sel = ($sel + $items.Count - 1) % $items.Count }
        elseif ($k -eq 'RightArrow' -or $k -eq 'DownArrow') { $sel = ($sel + 1) % $items.Count }
        elseif ($k -eq 'Enter') { $cmd = $items[$sel].Id }
        elseif ($k -eq 'R') { $cmd = 'retry' }
        elseif ($k -eq 'C') { $cmd = 'continue' }
        elseif ($k -eq 'Q') { $cmd = 'quit' }
        if ($cmd) { return $cmd }
    }
}

# returns State = current / outdated / offline (chose Continue offline) / quit
function Check-Update {
    $tries = 0
    while ($true) {
        $note = ''
        if ($tries -gt 0) { $note = 'Still no connection (tried ' + $tries + 'x, last at ' + (Get-Date).ToString('h:mm:ss tt') + ')' }
        $tries++
        if (-not (Test-GitHubReachable)) {
            $a = Show-NoInternet 'This PC is not connected to the internet.' $note
        } else {
            Prog-Begin 'Cyan' 'UPDATE CHECK' 'Checking for updates'
            Prog-Stage 'Reading the version on this PC...' 5
            $local = Get-LocalSha
            Prog-Stage 'Retrieving the latest version info from GitHub...' 15 70
            $remote = Get-RemoteSha
            if ($remote) {
                Prog-Stage 'Comparing versions...' 85
                $state = 'current'; $msg = 'Completed. You have the latest version.'; $hold = 12
                if ($remote -ne $local) { $state = 'outdated'; $msg = 'Completed. A newer version is available on GitHub.'; $hold = 8 }
                Prog-Stage $msg 100
                Prog-Hold $hold
                Prog-End
                return [pscustomobject]@{ State = $state; Local = $local; Remote = $remote }
            }
            Prog-End
            $a = Show-NoInternet 'The internet works, but GitHub did not answer.' $note
        }
        if ($a -eq 'continue') { return [pscustomobject]@{ State = 'offline'; Local = $null; Remote = $null } }
        if ($a -eq 'quit') { return [pscustomobject]@{ State = 'quit'; Local = $null; Remote = $null } }
    }
}

# the "update required" screen. $err (optional) = why the last try failed
function Show-UpdateRequired($st, $err = '') {
    $lbl = 'Update now'
    if ($err) { $lbl = 'Try again' }
    $items = @((BarItem 'U' $lbl 'Cyan' 'update'))
    if (-not $UpdateRequired) { $items += (BarItem 'S' 'Skip for now' 'Yellow' 'skip') }
    $items += (BarItem 'Q' 'Quit' 'Red' 'quit')
    $sel = 0
    $theme = 'Yellow'; $btxt = '  UPDATE REQUIRED  '; $bfg = 'Black'
    $intro = '  This PC does not have the latest version from GitHub.'
    if (-not $UpdateRequired) {
        $theme = 'Cyan'; $btxt = '  UPDATE AVAILABLE  '
        $intro = '  A new version of LSPDMS is on GitHub. Would you like to update?'
    }
    if ($err) { $theme = 'Red'; $btxt = '  UPDATE FAILED  '; $bfg = 'White' }
    $lv = 'unknown (not recorded yet)'
    if ($st.Local) { $lv = Short-Sha $st.Local }
    $rv = Short-Sha $st.Remote
    Wipe
    while ($true) {
        Frame $theme 'UPDATE' {
            Row
            BannerRow $btxt $bfg $theme
            Row
            Row (Pt $intro 'White')
            if (-not $UpdateRequired -and -not $err) { Row (Pt '  Skip for now keeps this version; you will be asked again next launch.' 'DarkGray') }
            Row
            Row (Pt '  This PC           ' 'DarkGray') (Pt $lv 'Yellow')
            Row (Pt '  Latest on GitHub  ' 'DarkGray') (Pt $rv 'Green')
            Row
            if ($err) { Row (Pt ('  ' + (Fit $err 68)) 'Red') }
            else { Row (Pt '  Updating downloads the latest files and replaces the old ones.' 'Gray') }
            Row (Pt '  Kept as they are: .env, storage (uploads) and the database.' 'DarkGray')
            Row
            SepLine
            ActionBar $items $sel
            HintRow ($G.Left + '  ' + $G.Right + '  move          Enter  select')
        }
        $k = [Console]::ReadKey($true).Key
        $cmd = ''
        if ($k -eq 'LeftArrow' -or $k -eq 'UpArrow') { $sel = ($sel + $items.Count - 1) % $items.Count }
        elseif ($k -eq 'RightArrow' -or $k -eq 'DownArrow') { $sel = ($sel + 1) % $items.Count }
        elseif ($k -eq 'Enter') { $cmd = $items[$sel].Id }
        elseif ($k -eq 'U') { $cmd = 'update' }
        elseif ($k -eq 'Q') { $cmd = 'quit' }
        elseif ($k -eq 'S' -and -not $UpdateRequired) { $cmd = 'skip' }
        if ($cmd) { return $cmd }
    }
}

function Get-LastRes($out) { @($out) | Where-Object { $_ -and $_.PSObject.Properties['State'] } | Select-Object -Last 1 }

# downloads the latest version and replaces the old files. Kept: .env, storage, vendor, node_modules (packages are re-synced after).
function Do-Update {
    if (-not (Test-InWww $Project)) { return (Res 'fail' ('project is not inside ' + (Get-WwwRoot) + ' - update blocked')) }
    if (-not (Read-SourcePassword)) { return (Res 'cancel' 'cancelled') }
    $script:FailLog = $null
    $res = $null
    try {
        Prog-Begin 'Yellow' 'UPDATING' 'Updating LSPDMS to the latest version'
        if (-not (Get-SourceFiles)) {
            $res = Res 'fail' 'download failed - check internet, $RepoUrl, repo access'
        } else {
            $src = $script:SrcDir
            Prog-Stage 'Preparing the project folder...' 74
            New-Item -ItemType Directory -Force $Project | Out-Null
            Prog-Stage 'Replacing the old files with the latest version...' 76 88
            $xd = '"' + $src + '\.git" "' + $src + '\vendor" "' + $src + '\node_modules" "' + $src + '\storage"'
            $arg = '"' + $src + '" "' + $Project + '" /E /IS /IT /R:1 /W:1 /XD ' + $xd + ' /XF .env ' + $VersionFile + ' /NFL /NDL /NJH /NJS /NP'
            $p = Start-Hidden 'robocopy.exe' $arg "$Logs\robocopy.log"
            Wait-Proc $p 600
            $code = $p.ExitCode
            if (-not (Test-Path "$Project\artisan") -or ($null -ne $code -and $code -ge 8)) {
                $script:FailLog = "$Logs\robocopy.log"
                $res = Res 'fail' ('could not replace the files (robocopy code ' + $code + ')')
            } else {
                Prog-Stage 'Updating Laravel packages (composer)...' 90 95
                $rv = Get-LastRes (Install-Vendor $true)
                Prog-Stage 'Updating JS packages (npm)...' 95 98
                $rn = Get-LastRes (Install-NodeMods $true)
                Prog-Stage 'Saving the new version...' 99
                Save-Version $script:SrcSha
                $state = 'ok'; $msg = 'Completed. Updated to the latest version.'
                if (($rv -and $rv.State -ne 'ok') -or ($rn -and $rn.State -ne 'ok')) {
                    $state = 'warn'; $msg = 'Completed. Updated - check packages in Required Dependencies.'
                }
                Prog-Stage $msg 100
                Prog-Hold 14
                $res = Res $state $msg
            }
        }
    } catch { $res = Res 'fail' $_.Exception.Message }
    Clear-SourceTemp
    Prog-End
    $res
}

# startup check. Returns 'continue' or 'quit'
function Run-UpdateCheck {
    if (-not (Test-Path "$Project\artisan")) { return 'continue' }
    if ($RepoUrl -notmatch '^https://') { return 'continue' }
    $err = ''
    $st = Check-Update
    if ($st.State -eq 'quit') { return 'quit' }
    while ($st.State -eq 'outdated') {
        $a = Show-UpdateRequired $st $err
        if ($a -eq 'quit') { return 'quit' }
        if ($a -eq 'skip') { return 'continue' }
        $r = Do-Update
        if ($r.State -eq 'ok' -or $r.State -eq 'warn') { return 'continue' }
        if ($r.State -eq 'cancel') { $err = '' } else { $err = [string]$r.Detail }
    }
    return 'continue'
}

function Do-Install($deps) {
    $keys = @($deps | Where-Object { $_.State -ne 'ok' -and $_.Fix } | ForEach-Object { $_.Fix } | Select-Object -Unique)
    $items = @()
    if ($keys -contains 'laragon')  { $items += (Mk 'Install Laragon (PHP, MySQL)' { Install-Tool 'laragon' }) }
    if ($keys -contains 'node')     { $items += (Mk 'Install Node.js + npm' { Install-Tool 'node' }) }
    if ($keys -contains 'phpext')   { $items += (Mk 'Enable PHP extensions (php.ini)' { Install-PhpExtensions }) }
    if ($keys -contains 'composer') { $items += (Mk 'Install Composer' { Install-Tool 'composer' }) }
    $gotSource = $false
    if ($keys -contains 'source') {
        if (Read-SourcePassword) {
            $gotSource = $true
            $items += (Mk 'Download source code (GitHub)' { Install-Source })
        }
    }
    # a freshly downloaded project also needs its packages + .env
    if ($keys -contains 'env' -or $gotSource)      { $items += (Mk 'Create .env + app key' { Install-Env }) }
    if ($keys -contains 'vendor' -or $gotSource)   { $items += (Mk 'Laravel packages (composer)' { Install-Vendor }) }
    if ($keys -contains 'nodemods' -or $gotSource) { $items += (Mk 'JS packages + Vite (npm)' { Install-NodeMods }) }
    if ($items.Count -eq 0) { return }
    $prevQuiet = $script:Quiet
    $script:Quiet = $false
    $script:Theme = 'Yellow'; $script:Badge = 'INSTALLING'; $script:Banner = $null
    $script:Steps = $items; $script:pct = 0; $script:spin = 0; $script:T0 = Get-Date
    Wipe
    Render-Steps $script:Theme $script:Badge
    foreach ($s in $items) {
        $s.State = 'run'; $s.Detail = 'working...'
        Pump
        $r = $null
        try {
            $out = @(& $s.Action)
            $r = $out | Where-Object { $_ -and $_.PSObject.Properties['State'] } | Select-Object -Last 1
        } catch { $r = Res 'fail' $_.Exception.Message }
        if (-not $r) { $r = Res 'ok' '' }
        $s.State = $r.State; $s.Detail = $r.Detail
        Pump
    }
    $script:Banner = @{ T = '  SETUP FINISHED  '; F = 'Black'; B = 'Green' }
    for ($i = 0; $i -lt 20; $i++) { Pump }
    $script:Banner = $null
    $script:Quiet = $prevQuiet
}

function Show-Checking($msg) {
    Wipe
    Frame 'Cyan' 'CHECKING' {
        Row
        Row (Pt ('  ' + $msg) 'White')
        Row
    }
}

function Show-Deps($deps, $fromMenu) {
    $bad = @($deps | Where-Object { $_.State -eq 'fail' }).Count
    $warn = @($deps | Where-Object { $_.State -eq 'warn' }).Count
    $fixable = @($deps | Where-Object { $_.State -ne 'ok' -and $_.Fix }).Count
    $theme = 'Green'; $btxt = '  ALL DEPENDENCIES FOUND  '; $bfg = 'Black'
    if ($bad -gt 0) { $theme = 'Red'; $btxt = '  SOMETHING IS MISSING  '; $bfg = 'White' }
    elseif ($warn -gt 0) { $theme = 'Yellow'; $btxt = '  CHECK THE WARNINGS  ' }
    $labels = @{ laragon = 'Laragon (PHP, MySQL)'; node = 'Node.js + npm'; composer = 'Composer'; phpext = 'PHP extensions'; vendor = 'Laravel packages'; nodemods = 'JS packages'; env = '.env + key'; source = 'Source code (GitHub, needs password)' }
    $will = @($deps | Where-Object { $_.State -ne 'ok' -and $_.Fix } | ForEach-Object { $labels[$_.Fix] } | Select-Object -Unique)
    $willTxt = ''
    if ($will.Count -gt 0) { $willTxt = 'Will set up: ' + ($will -join ', ') }
    $items = @()
    if ($fixable -gt 0) { $items += (BarItem 'I' 'Install missing' 'Cyan' 'install') }
    $items += (BarItem 'R' 'Re-check' 'Yellow' 'recheck')
    if ($fromMenu) { $items += (BarItem 'C' 'Back' 'Cyan' 'continue') }
    else { $items += (BarItem 'C' 'Continue' 'Cyan' 'continue'); $items += (BarItem 'Q' 'Quit' 'Red' 'quit') }
    $dSel = 0
    if ($fixable -eq 0) { for ($n = 0; $n -lt $items.Count; $n++) { if ($items[$n].Id -eq 'continue') { $dSel = $n } } }
    Wipe
    while ($true) {
        Frame $theme 'DEPENDENCIES' {
            Row
            BannerRow $btxt $bfg $theme
            Row
            Row (Pt '  Required Dependencies' 'White')
            Row
            foreach ($x in $deps) {
                $ic = $G.Ok; $ic_c = 'Green'; $d_c = 'DarkGray'
                if ($x.State -eq 'warn') { $ic = '!'; $ic_c = 'Yellow'; $d_c = 'Yellow' }
                if ($x.State -eq 'fail') { $ic = $G.Fail; $ic_c = 'Red'; $d_c = 'Red' }
                Row (Pt '  ') (Pt $ic $ic_c) (Pt ' ') (Pt ($x.Name.PadRight(20)) 'White') (Pt (Fit $x.Detail 46) $d_c)
            }
            Row
            Row (Pt ('  ' + (Fit $willTxt 68)) 'Gray')
            SepLine
            ActionBar $items $dSel
            HintRow ($G.Left + '  ' + $G.Right + '  move          Enter  select')
        }
        $k = [Console]::ReadKey($true).Key
        $cmd = ''
        if ($k -eq 'LeftArrow' -or $k -eq 'UpArrow') { $dSel = ($dSel + $items.Count - 1) % $items.Count }
        elseif ($k -eq 'RightArrow' -or $k -eq 'DownArrow') { $dSel = ($dSel + 1) % $items.Count }
        elseif ($k -eq 'Enter') { $cmd = $items[$dSel].Id }
        if ($cmd) { return $cmd }
    }
}

function Get-DepIssueCount($deps) {
    @($deps | Where-Object { $_.State -eq 'fail' -or ($_.State -eq 'warn' -and $_.Fix) }).Count
}

function Run-DepCheck($onlyIfProblems, $fromMenu) {
    $auto = ($AutoInstall -and -not $fromMenu)
    $autoTried = $false
    if ($auto) { [void](Enable-PhpExtensions) }   # switch on missing PHP extensions in php.ini before checking
    Show-Checking 'Checking required dependencies...'
    $deps = Get-Deps
    while ($true) {
        $probs = Get-DepIssueCount $deps
        $script:DepIssues = $probs
        if ($onlyIfProblems -and $probs -eq 0) { return 'continue' }
        # automatic mode: install / fix everything fixable once, without waiting for a button press
        if ($auto -and -not $autoTried -and @($deps | Where-Object { $_.State -ne 'ok' -and $_.Fix }).Count -gt 0) {
            $autoTried = $true
            Do-Install $deps; Add-ToolPaths
            Show-Checking 'Checking required dependencies...'
            $deps = Get-Deps
            continue
        }
        $a = Show-Deps $deps $fromMenu
        if ($a -eq 'install') { Do-Install $deps; Add-ToolPaths }
        elseif ($a -ne 'recheck') { return $a }
        Show-Checking 'Checking required dependencies...'
        $deps = Get-Deps
    }
}

# true only when Windows Mobile hotspot is really switched on (the 192.168.137.1 address alone can stay behind after it is turned off)
function Test-HotspotOn {
    try {
        $null = [Windows.Networking.Connectivity.NetworkInformation, Windows.Networking.Connectivity, ContentType = WindowsRuntime]
        $null = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager, Windows.Networking.NetworkOperators, ContentType = WindowsRuntime]
        $prof = [Windows.Networking.Connectivity.NetworkInformation]::GetInternetConnectionProfile()
        if ($prof) {
            $mgr = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager]::CreateFromConnectionProfile($prof)
            return ([string]$mgr.TetheringOperationalState -eq 'On')
        }
    } catch {}
    # fallback: the hotspot address must sit on an adapter that is really Up
    $a = Get-NetIPAddress -IPAddress '192.168.137.1' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($a) {
        $ad = Get-NetAdapter -InterfaceIndex $a.InterfaceIndex -ErrorAction SilentlyContinue
        return [bool]($ad -and $ad.Status -eq 'Up')
    }
    return $false
}

# live checks shown under "Team / network" (nothing here assigns an IP - it only looks at what Windows already has)
function Get-LanChecks($ip) {
    $c = @()
    if ($ip) { $c += [pscustomobject]@{ State = 'ok'; Text = ('Connected: ' + $script:LanIf + ' (IP found automatically)'); Fix = '' } }
    else     { $c += [pscustomobject]@{ State = 'fail'; Text = 'No network found'; Fix = 'Join the SAME Wi-Fi / router as your team, then Refresh network' } }
    if ($ip) {
        try {
            $pr = Get-NetConnectionProfile -InterfaceAlias $script:LanIf -ErrorAction Stop
            if ($pr.NetworkCategory -eq 'Public') {
                $c += [pscustomobject]@{ State = 'warn'; Text = 'Network profile is Public (blocks teammates)'; Fix = 'Settings > Network & internet > your network > Private' }
            } else { $c += [pscustomobject]@{ State = 'ok'; Text = 'Network profile is Private'; Fix = '' } }
        } catch {}
    }
    $vpn = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' -and ($_.InterfaceDescription -match 'WARP|VPN|Tailscale|ZeroTier|WireGuard|Wintun' -or $_.Name -match 'WARP|VPN|Tailscale|ZeroTier|WireGuard') })
    if ($vpn.Count -gt 0) { $c += [pscustomobject]@{ State = 'warn'; Text = 'VPN / Cloudflare WARP is on'; Fix = 'Turn it off, teammates may not reach this PC' } }
    else { $c += [pscustomobject]@{ State = 'ok'; Text = 'No VPN / Cloudflare WARP running'; Fix = '' } }
    if (Test-HotspotOn) {
        $c += [pscustomobject]@{ State = 'warn'; Text = 'Windows Mobile hotspot is on'; Fix = 'Settings > Network & internet > Mobile hotspot > Off' }
    }
    $c
}

function New-MenuItems($running, $issues) {
    $dl = 'Required Dependencies'; $dc = 'Cyan'
    if ($issues -gt 0) { $dl = 'Required Dependencies  ! ' + $issues + ' missing'; $dc = 'Red' }
    (BarItem 'R' 'Refresh network' 'Yellow' 'refresh')
    (BarItem 'D' $dl $dc 'deps')
    if ($running) { BarItem 'B' 'Back' 'Cyan' 'quit' } else { BarItem 'Q' 'Quit' 'Red' 'quit' }
}

function Show-ModeMenu($running = $false) {
    $note = ''
    $script:Quiet = $true
    $selRow = 0; $selCol = 0
    if ($running -and -not $LanMode) { $selRow = 1 }   # start on the mode you are in now
    if ($null -eq $script:DepIssues) {
        if ($CheckDeps) { Show-Checking 'Checking required dependencies...'; $script:DepIssues = Get-DepIssueCount (Get-Deps) }
        else { $script:DepIssues = 0 }
    }
    $mItems = @(New-MenuItems $running $script:DepIssues)
    try {
        while ($true) {
            $ip = Get-LanIp
            if (-not $ip) { $note = Get-NetHint }
            $checks = @(Get-LanChecks $ip)
            $lastSig = (($checks | ForEach-Object { $_.State + $_.Text }) -join '|') + $ip
            Wipe
            $draw = $true
            $lastScan = Get-Date
            $pick = $null
            while (-not $pick) {
                # auto-detect: redraw by itself when the network or its status changes
                if (((Get-Date) - $lastScan).TotalSeconds -ge 3) {
                    $lastScan = Get-Date
                    $new = Get-LanIp
                    $chk = @(Get-LanChecks $new)
                    $sig = (($chk | ForEach-Object { $_.State + $_.Text }) -join '|') + $new
                    if ($new -ne $ip) {
                        $ip = $new
                        if ($new) { $note = "Network found: $new" }
                        else {
                            $note = Get-NetHint
                            if (-not $note) { $note = 'Network lost - check your connection' }
                        }
                    }
                    if ($sig -ne $lastSig) { $lastSig = $sig; $checks = $chk; $draw = $true }
                }
                if ($draw) {
                    $draw = $false
                    Frame 'Cyan' 'SETUP' {
                        $on1 = ($selRow -eq 0); $on2 = ($selRow -eq 1)
                        $dc1 = 'DarkGray'; if ($on1) { $dc1 = 'Gray' }
                        $dc2 = 'DarkGray'; if ($on2) { $dc2 = 'Gray' }
                        $barSel = -1; if ($selRow -eq 2) { $barSel = $selCol }
                        Row
                        Row (Pt '  How will LSPDMS be used?' 'White')
                        BoxTop $on1
                        OptRow $on1 'Team / network' 'work together on one network (LAN)'
                        if ($ip) { BoxRow $on1 (Pt '     Address  ' 'DarkGray') (Pt "http://${ip}:$AppPort" 'Green') }
                        else     { BoxRow $on1 (Pt '     Address  ' 'DarkGray') (Pt 'waiting for a network...' 'Yellow') }
                        foreach ($ck in $checks) {
                            $ic = $G.Ok; $icc = 'Green'; $tc = $dc1
                            if ($ck.State -eq 'warn') { $ic = '!'; $icc = 'Yellow'; $tc = 'Yellow' }
                            if ($ck.State -eq 'fail') { $ic = $G.Fail; $icc = 'Red'; $tc = 'Red' }
                            BoxRow $on1 (Pt '     ') (Pt $ic $icc) (Pt ' ') (Pt (Fit $ck.Text 60) $tc)
                        }
                        $fx = $checks | Where-Object { $_.State -ne 'ok' -and $_.Fix } | Select-Object -First 1
                        if ($fx) { BSub $on1 ('  ' + $G.Right + ' ' + (Fit $fx.Fix 58)) 'Yellow' }
                        BSub $on1 'The whole team works at the same time. A bit slower.' $dc1
                        BoxBot $on1
                        BoxTop $on2
                        OptRow $on2 'Just this device' 'only this PC'
                        BoxRow $on2 (Pt '     Address  ' 'DarkGray') (Pt "http://127.0.0.1:$AppPort" 'Green')
                        BSub $on2 'A bit faster, but only one person at a time. Nothing to set up.' $dc2
                        BoxBot $on2
                        if ($note -or $script:DepIssues -le 0) { Row (Pt ('  ' + (Fit $note 68)) 'Yellow') }
                        else { Row (Pt ('  ! ' + $script:DepIssues + ' problem(s) found - open Required Dependencies to fix them') 'Red') }
                        SepLine
                        ActionBar $mItems $barSel
                        HintRow ($G.Up + ' ' + $G.Down + '  move          ' + $G.Left + ' ' + $G.Right + '  bottom buttons          Enter  select')
                    }
                }
                if ([Console]::KeyAvailable) {
                    $k = [Console]::ReadKey($true).Key
                    $cmd = ''
                    if ($k -eq 'UpArrow') { $selRow = ($selRow + 2) % 3; $draw = $true }
                    elseif ($k -eq 'DownArrow') { $selRow = ($selRow + 1) % 3; $draw = $true }
                    elseif ($k -eq 'LeftArrow') { if ($selRow -eq 2) { $selCol = ($selCol + 2) % 3; $draw = $true } }
                    elseif ($k -eq 'RightArrow') { if ($selRow -eq 2) { $selCol = ($selCol + 1) % 3; $draw = $true } }
                    elseif ($k -eq 'Enter') {
                        if ($selRow -eq 0) { $cmd = 'lan' }
                        elseif ($selRow -eq 1) { $cmd = 'local' }
                        else { $cmd = $mItems[$selCol].Id }
                    }

                    if ($cmd -eq 'lan' -or $cmd -eq 'local' -or $cmd -eq 'quit') { $pick = $cmd }
                    elseif ($cmd -eq 'deps') { [void](Run-DepCheck $false $true); $mItems = @(New-MenuItems $running $script:DepIssues); $script:Quiet = $true; Wipe; $draw = $true; $lastScan = Get-Date }
                    elseif ($cmd -eq 'refresh') {
                        Wipe
                        Frame 'Cyan' 'SETUP' {
                            Row
                            Row (Pt '  Refreshing the network...' 'White')
                            Row
                            Row (Pt '  Looking for a network address. If none is found this may' 'Gray')
                            Row (Pt '  renew the IP and restart the Wi-Fi adapter (up to ~1 min).' 'Gray')
                            Row
                        }
                        $note = Refresh-Network
                        $ip = Get-LanIp
                        $checks = @(Get-LanChecks $ip)
                        $lastSig = (($checks | ForEach-Object { $_.State + $_.Text }) -join '|') + $ip
                        Wipe
                        $lastScan = Get-Date
                        $draw = $true
                    }
                } else {
                    Start-Sleep -Milliseconds 150
                }
            }
            if ($pick -ne 'lan') { return $pick }

            # network chosen: make sure there really is a connection, otherwise warn and go back to the choice
            $ok = [bool](Get-LanIp)
            if ($ok -and $RequireInternet) { $ok = Test-Internet }
            if ($ok) { return 'lan' }
            Show-NoNetwork
            $note = ''
        }
    } finally {
        $script:Quiet = $false
    }
}

# ----------------------------------- main -----------------------------------
try {
    $Project = Resolve-Project
    Add-ToolPaths
    foreach ($sf in @($NetStateFile, (Join-Path $env:TEMP 'lspdms\static-if.txt'))) {   # the second one = where older launchers kept it
        if (-not $script:StaticIf -and (Test-Path -LiteralPath $sf)) { $script:StaticIf = [int](Get-Content -LiteralPath $sf -ErrorAction SilentlyContinue | Select-Object -First 1) }
    }
    $ui = $Host.UI.RawUI
    $ui.WindowTitle = 'LSPDMS'
    $ui.BackgroundColor = 'Black'
    Disable-QuickEdit
    try { $ui.WindowSize = New-Object Management.Automation.Host.Size(80, 36) } catch {}
    try { $ui.BufferSize = New-Object Management.Automation.Host.Size(80, 36) } catch {}
    try { $ui.WindowSize = New-Object Management.Automation.Host.Size(80, 36) } catch {}

    # the launcher does nothing unless it was started as Administrator
    if (-not (Test-Admin)) {
        Show-AdminRequired
        exit
    }

    Set-LauncherPaths

    # "LSPDMS_Launcher.bat stop": end only the programs the launcher started (pids.json), then close
    if ($LaunchArgs -contains 'stop') {
        $n = @(Read-PidFile | Where-Object { Get-RecordedProc $_ }).Count
        Stop-Tracked @()
        Remove-LanFirewall
        Write-LaunchLog ('=== stop command: ' + $n + ' process(es) stopped')
        Wipe
        Frame 'Magenta' 'STOP' {
            Row
            BannerRow '  LSPDMS STOPPED  ' 'White' 'DarkMagenta'
            Row
            Row (Pt ('  Stopped ' + $n + ' program(s) the launcher had started.') 'White')
            Row (Pt '  Programs started some other way were left running.' 'Gray')
            Row
        }
        Start-Sleep -Milliseconds 1500
        exit
    }

    # a second copy of the launcher would fight over the same ports - stop it here
    $createdNew = $false
    $script:Lock = New-Object System.Threading.Mutex($true, 'Global\LSPDMS_Launcher', [ref]$createdNew)
    if (-not $createdNew) {
        Show-AlreadyRunning
        exit
    }

    $mode = ''
    if ($LaunchArgs -contains 'lan') { $mode = 'lan' } elseif ($LaunchArgs -contains 'local') { $mode = 'local' }
    if ($mode -ne 'lan' -and $mode -ne 'local') {
        if ($AskMode) { $mode = Show-ModeMenu } else { $mode = $DefaultMode }
    }
    if ($mode -eq 'quit') { exit }
    $LanMode = ($mode -eq 'lan')

    # network mode needs Administrator once, to open the firewall for other devices
    if ($LanMode -and ($OpenFirewall -or $RenewIp) -and $f -and -not (Test-Admin)) {
        try {
            Start-Process -FilePath $env:ComSpec -ArgumentList "/c `"`"$f`" lan`"" -Verb RunAs -ErrorAction Stop
            exit
        } catch { }
    }

    [void](Fix-EnvDatabase)
    Show-Splash
    if ($CheckDeps) {
        $dc = Run-DepCheck $true $false
        if ($dc -eq 'quit') { Show-Shutdown; exit }
    }
    if ($CheckUpdates) {
        $uc = Run-UpdateCheck
        if ($uc -eq 'quit') { Show-Shutdown; exit }
    }
    while ($true) {
        $r = Do-Deploy
        if ($r -eq 'fail') {
            $k = Show-Fail
            if ($k -eq 'retry') { Stop-All; continue }
            Show-Shutdown; break
        } else {
            $ui.WindowTitle = "LSPDMS - ONLINE - $(Get-OpenUrl)"
            $k = Show-Online
            while ($k -eq 'switch') {
                $m = Show-ModeMenu $true
                if ($m -eq 'lan' -or $m -eq 'local') {
                    $LanMode = ($m -eq 'lan')
                    $script:LanIp = $null; $script:LanUrl = $null; $script:BindHost = $AppHost
                    if (-not $LanMode) { try { [void][Lsp.Pwr]::SetThreadExecutionState([uint32]2147483648) } catch {} }
                    $k = 'restart'
                } else {
                    $k = Show-Online   # Back: nothing changes
                }
            }
            if ($k -eq 'restart') { Stop-All; continue }
            Show-Shutdown; break
        }
    }
} catch {
    [Console]::CursorVisible = $true
    [Console]::ResetColor()
    Write-Host ''
    Write-Host "LSPDMS launcher crashed: $($_.Exception.Message)" -ForegroundColor Red
    Read-Host 'Press Enter to close'
} finally {
    try { if ($script:Lock) { $script:Lock.Close() } } catch {}
    Restore-Network
    [Console]::CursorVisible = $true
    [Console]::ResetColor()
}
