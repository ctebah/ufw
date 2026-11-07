param(
    [Parameter(Position = 0)]
    [ValidateSet('up', 'down', 'status')]
    [string] $Action = 'up'
)

$ErrorActionPreference = 'Continue'
Set-Location (Join-Path $PSScriptRoot '..')

switch ($Action) {
    'up' {
        & docker compose up -d mysql
        exit $LASTEXITCODE
    }
    'down' {
        & docker compose down
        exit $LASTEXITCODE
    }
    'status' {
        & docker compose ps
        exit $LASTEXITCODE
    }
}
