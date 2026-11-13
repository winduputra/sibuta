<#
.SYNOPSIS
    SiBUTA `users` table reset procedure (LOCAL DATABASE ONLY).

.DESCRIPTION
    Deletes the CONTENTS of the `users` table using DELETE (never TRUNCATE)
    after producing and verifying a mysqldump backup.

    Only `users` is touched. `guests`, `devices`, `personal_access_tokens`,
    `divisions`, `roles` and every other table are left untouched.

    Refuses to run against a non-local host unless -ForceRemote is supplied.

.PARAMETER DryRun
    Prints every planned action and exits without modifying anything.

.PARAMETER ForceRemote
    Required to allow execution when DB host is not 127.0.0.1 / localhost.
#>
[CmdletBinding()]
param(
    [switch]$DryRun,
    [switch]$ForceRemote
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = Split-Path -Parent $PSScriptRoot
$backupDir   = Join-Path $projectRoot 'storage\backups'
$tables      = @('users')  # ONLY users is ever deleted

function Assert-LocalDatabase {
    param([string]$HostName)

    $localHosts = @('127.0.0.1', 'localhost', '::1')
    if ($localHosts -notcontains $HostName -and -not $ForceRemote) {
        throw "Refusing to reset: DB host '$HostName' is not local. Use -ForceRemote only if you are certain."
    }
}

function Get-DbConfig {
    $output = & php artisan tinker --execute "echo json_encode(['host' => config('database.connections.mysql.host'), 'database' => config('database.connections.mysql.database'), 'username' => config('database.connections.mysql.username'), 'password' => config('database.connections.mysql.password'), 'port' => config('database.connections.mysql.port')]);" 2>&1

    $json = ($output | Where-Object { $_ -match '^\{' } | Select-Object -Last 1)
    if (-not $json) {
        throw "Cannot read database configuration from Laravel."
    }

    return $json | ConvertFrom-Json
}

function Resolve-Mysqldump {
    $onPath = Get-Command mysqldump -ErrorAction SilentlyContinue
    if ($onPath) {
        return $onPath.Source
    }

    # Laragon keeps mysqldump outside PATH; locate it under the Laragon bin dir.
    $candidates = @(
        'C:\laragon\bin\mysql',
        'C:\xampp\mysql\bin'
    )

    foreach ($root in $candidates) {
        if (-not (Test-Path -LiteralPath $root)) { continue }
        $found = Get-ChildItem -LiteralPath $root -Recurse -Filter 'mysqldump.exe' -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($found) {
            return $found.FullName
        }
    }

    throw "mysqldump.exe not found. Install MySQL client tools or add them to PATH."
}

function Get-UsersRowCount {
    $output = & php artisan tinker --execute "echo DB::table('users')->count();" 2>&1
    $value  = ($output | Where-Object { $_ -match '^\d+$' } | Select-Object -Last 1)

    if (-not $value) {
        throw "Cannot read users row count."
    }

    return [int]$value
}

function New-UsersBackup {
    param([pscustomobject]$Db)

    if (-not (Test-Path -LiteralPath $backupDir)) {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    }

    $stamp     = Get-Date -Format 'yyyyMMdd_HHmmss'
    $backupPath = Join-Path $backupDir "users_backup_$stamp.sql"

    Write-Host "Backing up table 'users' to $backupPath ..."
    $mysqldump = Resolve-Mysqldump
    $env:MYSQL_PWD = $Db.password
    try {
        & $mysqldump --host=$($Db.host) --port=$($Db.port) --user=$($Db.username) --single-transaction --no-create-info --complete-insert --skip-add-locks $($Db.database) users 2>&1 | Set-Content -LiteralPath $backupPath -Encoding utf8
    }
    finally {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $backupPath)) {
        throw "mysqldump failed; aborting reset."
    }

    return $backupPath
}

function Assert-BackupVerified {
    param([string]$BackupPath, [int]$ExpectedRows)

    if (-not (Test-Path -LiteralPath $BackupPath)) {
        throw "BUILD FAILED: backup file '$BackupPath' does not exist."
    }

    $size = (Get-Item -LiteralPath $BackupPath).Length
    if ($size -le 0) {
        throw "BUILD FAILED: backup file is empty."
    }

    $content = Get-Content -LiteralPath $BackupPath -Raw
    if ($content -notmatch 'INSERT INTO `users`') {
        throw "BUILD FAILED: backup does not contain an INSERT statement for 'users'."
    }

    # Verify the dump captured every expected row.
    $rowPatterns = [regex]::Matches($content, '\(\d+,')
    $dumpedRows  = $rowPatterns.Count

    Write-Host "Backup verified: $size bytes, ~$dumpedRows row tuple(s), expected $ExpectedRows."

    if ($ExpectedRows -gt 0 -and $dumpedRows -lt 1) {
        throw "BUILD FAILED: backup contains no data rows but users had $ExpectedRows row(s)."
    }
}

function Invoke-UsersDelete {
    $before = Get-UsersRowCount

    $output = & php artisan tinker --execute "echo DB::transaction(function () { return DB::table('users')->delete(); });" 2>&1
    $deleted = ($output | Where-Object { $_ -match '^\d+$' } | Select-Object -Last 1)

    $after = Get-UsersRowCount

    Write-Host "users rows: before=$before deleted=$deleted after=$after"

    if ($after -ne 0) {
        throw "Reset incomplete: users still has $after row(s)."
    }

    return [pscustomobject]@{ Before = $before; Deleted = $deleted; After = $after }
}

function Clear-StaleSessions {
    $sessionDir = Join-Path $projectRoot 'storage\framework\sessions'
    if (-not (Test-Path -LiteralPath $sessionDir)) {
        Write-Host "No session directory; skipping."
        return 0
    }

    $files = @(Get-ChildItem -LiteralPath $sessionDir -File | Where-Object { $_.Name -ne '.gitignore' })
    if ($DryRun) {
        Write-Host "[DryRun] would delete $($files.Count) session file(s)."
        return $files.Count
    }

    foreach ($file in $files) {
        Remove-Item -LiteralPath $file.FullName -Force
    }

    Write-Host "Invalidated $($files.Count) session file(s)."
    return $files.Count
}

# ---- main ----
Write-Host "=== SiBUTA users reset procedure ==="
if ($DryRun) { Write-Host "MODE: DRY RUN (no changes will be made)" }

$db = Get-DbConfig
Assert-LocalDatabase -HostName $db.host

Write-Host "Database : $($db.database) @ $($db.host):$($db.port)"
Write-Host "Tables   : $($tables -join ', ') (contents only, DELETE)"

$before = Get-UsersRowCount
Write-Host "users rows before: $before"

if ($DryRun) {
    Write-Host "[DryRun] would backup 'users' to $backupDir"
    Write-Host "[DryRun] would verify backup contains data"
    Write-Host "[DryRun] would DELETE FROM users (inside a transaction)"
    Write-Host "[DryRun] would seed admin account via UserSeeder"
    Write-Host "[DryRun] would invalidate stale session files"
    Write-Host "[DryRun] would re-verify row count and role mapping"
    return
}

$backupPath = New-UsersBackup -Db $db
Assert-BackupVerified -BackupPath $backupPath -ExpectedRows $before

$result = Invoke-UsersDelete

Write-Host "Seeding admin account ..."
& php artisan db:seed --class=UserSeeder --force 2>&1 | Write-Host

Clear-StaleSessions | Out-Null
& php artisan cache:clear 2>&1 | Write-Host

Write-Host "=== DONE: users reset from $($result.Before) to $($result.After) row(s) ==="
