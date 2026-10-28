param(
    [switch] $WithDatabase
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$logDirectory = Join-Path $projectRoot 'var\checks'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

function Invoke-PhpCheck {
    param([string] $Name, [string[]] $PhpArguments)
    $lines = & php @PhpArguments 2>&1
    $resultCode = $LASTEXITCODE
    $lines | Set-Content -LiteralPath (Join-Path $logDirectory "$Name.log") -Encoding UTF8
    $lines | Select-Object -Last 3 | ForEach-Object { Write-Output $_ }
    if ($resultCode -ne 0) { throw "FAILED: $Name (exit $resultCode). See var/checks/$Name.log" }
    Write-Output "PASS: $Name"
}

Push-Location $projectRoot
try {
    Invoke-PhpCheck 'phpunit' @('vendor/phpunit/phpunit/phpunit', '--no-progress')
    Invoke-PhpCheck 'twig' @('bin/console', 'lint:twig', 'templates')
    Invoke-PhpCheck 'yaml' @('bin/console', 'lint:yaml', 'config')
    Invoke-PhpCheck 'container' @('bin/console', 'lint:container')
    foreach ($name in @('phase5_smoke','phase6_smoke','money_smoke','password_input_smoke')) {
        Invoke-PhpCheck $name @("tests/$name.php")
    }
    if ($WithDatabase) {
        foreach ($name in @('phase2_smoke','phase3_smoke','phase4_smoke','logout_smoke','phase7_smoke','cart_checkout_smoke','member_schema_smoke')) {
            Invoke-PhpCheck $name @("tests/$name.php")
        }
    }
    Write-Output 'All selected checks passed. Full logs: var/checks/'
} finally {
    Pop-Location
}
