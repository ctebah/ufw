$ErrorActionPreference = 'Continue'
Set-Location (Join-Path $PSScriptRoot '..')

$composeArgs = @('compose', 'run', '--rm', 'php')
if ($args.Count -gt 0) {
    $composeArgs += $args
}

& docker @composeArgs
$code = $LASTEXITCODE

if ($env:UFW_DOCKER_KEEP -ne '1') {
    & docker compose down | Out-Host
}

exit $code
