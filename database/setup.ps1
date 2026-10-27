param(
    [switch] $Reset
)

$ErrorActionPreference = 'Stop'

$databaseName = 'BanSachDB'
$sqlFile = Join-Path $PSScriptRoot 'BansachDB_Full_CoDuLieu.sql'
$sqlcmd = Get-Command sqlcmd -ErrorAction Stop

if (-not (Test-Path -LiteralPath $sqlFile)) {
    throw "Missing database script: $sqlFile"
}

$databaseState = & $sqlcmd.Source -S '.' -E -C -h -1 -W -Q "SET NOCOUNT ON; SELECT CASE WHEN DB_ID(N'$databaseName') IS NULL THEN 0 ELSE 1 END;"
if ($LASTEXITCODE -ne 0) {
    throw 'Cannot connect to the local SQL Server instance with Windows Authentication.'
}

$databaseExists = @($databaseState | Where-Object { $_.Trim() })[0].Trim() -eq '1'

if ($databaseExists -and -not $Reset) {
    throw "$databaseName already exists. Re-run with -Reset only when you intend to replace its six application tables and sample data."
}

if (-not $databaseExists) {
    & $sqlcmd.Source -S '.' -E -C -b -Q "CREATE DATABASE [$databaseName];"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create $databaseName."
    }
}

& $sqlcmd.Source -S '.' -E -C -b -r1 -f 65001 -d $databaseName -i $sqlFile
if ($LASTEXITCODE -ne 0) {
    throw "Failed to import $sqlFile into $databaseName."
}

& $sqlcmd.Source -S '.' -E -C -b -W -s '|' -d $databaseName -Q "SET NOCOUNT ON; SELECT 'LOAI_SACH' AS table_name, COUNT(*) AS row_count FROM dbo.LOAI_SACH UNION ALL SELECT 'SACH', COUNT(*) FROM dbo.SACH UNION ALL SELECT 'THANH_VIEN', COUNT(*) FROM dbo.THANH_VIEN UNION ALL SELECT 'QTV', COUNT(*) FROM dbo.QTV UNION ALL SELECT 'GIO_HANG', COUNT(*) FROM dbo.GIO_HANG UNION ALL SELECT 'MUA', COUNT(*) FROM dbo.MUA;"
if ($LASTEXITCODE -ne 0) {
    throw 'Database import finished, but verification failed.'
}
