#requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RootDir = Split-Path -Parent $PSScriptRoot
Set-Location $RootDir

$Compose = @('docker', 'compose')
$ComposeFiles = @('-f', 'compose.yaml')

if ($env:TM_CI -eq '1' -or $env:CI -eq 'true' -or $env:GITHUB_ACTIONS -eq 'true') {
    $ComposeFiles += @('-f', 'compose.ci.yaml')
}

function Invoke-ComposeCore {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)
    & $Compose @ComposeFiles @Args
    return $LASTEXITCODE
}

function Invoke-Compose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)
    $exitCode = Invoke-ComposeCore @Args
    if ($exitCode -ne 0) {
        throw "docker compose failed with exit code $exitCode"
    }
}

function Write-PackagistWarning {
    if ($env:COMPOSER_PACKAGIST_URL) {
        Write-Warning "COMPOSER_PACKAGIST_URL is set ($($env:COMPOSER_PACKAGIST_URL)). Custom Packagist mirrors can cause stale or incomplete installs."
    }
}

function Get-ComposerEnvArgs {
    if ($env:COMPOSER_PACKAGIST_URL) {
        return @('-e', "COMPOSER_PACKAGIST_URL=$($env:COMPOSER_PACKAGIST_URL)")
    }

    return @()
}

function Invoke-ComposerInstallWithRetry {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$ComposerArgs)

    Write-PackagistWarning
    $envArgs = Get-ComposerEnvArgs
    $attempt = 1
    $delaySeconds = 5

    while ($attempt -le 3) {
        $exitCode = Invoke-ComposeCore @('run', '--rm') + $envArgs + @('app', 'composer', 'install') + $ComposerArgs
        if ($exitCode -eq 0) {
            return
        }

        if ($attempt -eq 3) {
            throw 'Composer failed after 3 attempts.'
        }

        Write-Warning "Composer attempt $attempt failed; retrying in ${delaySeconds}s..."
        Start-Sleep -Seconds $delaySeconds
        $delaySeconds *= 2
        $attempt++
    }
}

function Show-Usage {
    @'
TallyMark Docker toolchain

Usage:
  .\scripts\tm.ps1 <verb> [args...]

Verbs:
  up           Start app, mysql, mailpit, and demosite
  down         Stop and remove containers
  bootstrap    Install dependencies, prepare env, and migrate database
  composer     Run Composer via the app container
  artisan      Run Artisan via the app container
  npm          Run npm via the node container (dev profile)
  test         Run the PHPUnit suite
  e2e          Run the frontend end-to-end suite
  load         Run the synthetic ingest load fixture
  release      Build the production release zip
  deploy       Build and deploy the production release
  shell        Open a shell in the app container
  help         Show this help
'@ | Write-Output
}

function Invoke-Up { Invoke-Compose @('up', '-d', '--build', 'mysql', 'mailpit', 'demosite', 'app') }
function Invoke-Down { param([string[]]$Args) Invoke-Compose (@('down') + $Args) }

function Invoke-Bootstrap {
    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        Write-Output 'Created .env from .env.example'
    }

    Invoke-Compose @('up', '-d', '--build', 'mysql', 'mailpit', 'demosite')
    Invoke-Compose @('up', '-d', '--wait', 'mysql')
    Invoke-ComposerInstallWithRetry
    Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan', 'key:generate', '--force')
    Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan', 'migrate', '--force')

    if (Test-Path 'frontend/package.json') {
        Invoke-Compose @('--profile', 'dev', 'run', '--rm', 'node', 'npm', '--prefix', 'frontend', 'ci')
    }

    Invoke-Compose @('up', '-d', '--build', 'app')
    Write-Output 'Bootstrap complete.'
}

function Invoke-Composer {
    param([string[]]$Args)
    if ($Args.Count -gt 0 -and $Args[0] -eq 'install') {
        $installArgs = if ($Args.Count -gt 1) { $Args[1..($Args.Count - 1)] } else { @() }
        Invoke-ComposerInstallWithRetry @installArgs
        return
    }

    Write-PackagistWarning
    Invoke-Compose (@('run', '--rm') + (Get-ComposerEnvArgs) + @('app', 'composer') + $Args)
}

function Invoke-Artisan { param([string[]]$Args) Invoke-Compose (@('run', '--rm', 'app', 'php', 'artisan') + $Args) }
function Invoke-Npm { param([string[]]$Args) Invoke-Compose (@('--profile', 'dev', 'run', '--rm', '--service-ports', 'node', 'npm') + $Args) }
function Invoke-Test { param([string[]]$Args) Invoke-Compose (@('run', '--rm', 'app', 'php', 'artisan', 'test') + $Args) }

function Invoke-E2e {
    param([string[]]$Args)
    if (-not (Test-Path 'frontend/package.json')) { throw 'The frontend E2E suite is introduced in PR16.' }
    Invoke-Compose (@('--profile', 'dev', 'run', '--rm', '--service-ports', 'node', 'npm', '--prefix', 'frontend', 'run', 'e2e', '--') + $Args)
}

function Invoke-Load {
    param([string[]]$Args)
    if (-not (Test-Path 'tests/Support/GeneratesBufferFixtures.php')) { throw 'The synthetic load fixture is introduced in PR15.' }
    Invoke-Compose (@('run', '--rm', 'app', 'php', 'artisan', 'analytics:load') + $Args)
}

function Invoke-Release {
    if (-not (Test-Path 'docker/release/Dockerfile')) { throw 'The release pipeline is introduced in PR14.' }
    New-Item -ItemType Directory -Force -Path 'dist' | Out-Null
    docker build -f docker/release/Dockerfile --target export --output 'type=local,dest=./dist' .
    if ($LASTEXITCODE -ne 0) { throw "Release build failed with exit code $LASTEXITCODE" }
    bash "$PSScriptRoot/validate-release.sh" (Resolve-Path 'dist').Path
    if ($LASTEXITCODE -ne 0) { throw "Release validation failed with exit code $LASTEXITCODE" }
}

function Invoke-Deploy {
    param([string[]]$Args)
    if (-not (Test-Path 'scripts/deploy.sh')) { throw 'Deployment tooling is introduced in PR14.' }
    Invoke-Release
    bash "$PSScriptRoot/deploy.sh" @Args
    if ($LASTEXITCODE -ne 0) { throw "Deployment failed with exit code $LASTEXITCODE" }
}

function Invoke-Shell { Invoke-Compose @('run', '--rm', 'app', 'bash') }

$Verb = if ($args.Count -gt 0) { $args[0] } else { 'help' }
$VerbArgs = if ($args.Count -gt 1) { $args[1..($args.Count - 1)] } else { @() }

switch ($Verb) {
    'up' { Invoke-Up }
    'down' { Invoke-Down -Args $VerbArgs }
    'bootstrap' { Invoke-Bootstrap }
    'composer' { Invoke-Composer -Args $VerbArgs }
    'artisan' { Invoke-Artisan -Args $VerbArgs }
    'npm' { Invoke-Npm -Args $VerbArgs }
    'test' { Invoke-Test -Args $VerbArgs }
    'e2e' { Invoke-E2e -Args $VerbArgs }
    'load' { Invoke-Load -Args $VerbArgs }
    'release' { Invoke-Release }
    'deploy' { Invoke-Deploy -Args $VerbArgs }
    'shell' { Invoke-Shell }
    { $_ -in @('help', '-h', '--help') } { Show-Usage }
    default { throw "Unknown verb: $Verb" }
}
