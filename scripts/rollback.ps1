param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $Version
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.env.docker')) {
    throw 'Create .env.docker from .env.docker.example before rolling back.'
}

$env:APP_VERSION = $Version
docker compose --env-file .env.docker pull app
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose --env-file .env.docker up -d --no-build --wait app
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Host "Application rolled back to version $Version."
