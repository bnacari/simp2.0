param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$Msg
)

$ErrorActionPreference = "Stop"

function Write-Step($step, $text) { Write-Host "[$step] $text" -ForegroundColor Green }
function Write-Title($text) { Write-Host $text -ForegroundColor Yellow }

Write-Host ""
Write-Title "== Git Deploy - develop =="
Write-Host ""

try {
    Write-Step "1/3" "Commit na develop..."
    git add .
    if ($LASTEXITCODE -ne 0) { throw "Falha no git add" }

    git commit -m $Msg
    if ($LASTEXITCODE -ne 0) { throw "Falha no git commit" }

    Write-Step "2/3" "Push origin..."
    git push
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push origin" }

    Write-Step "3/3" "Push github..."
    git push github
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push github" }

    Write-Host ""
    Write-Host "Deploy develop concluido com sucesso!" -ForegroundColor Green
    Write-Host ""
}
catch {
    Write-Host ""
    Write-Host "Erro: $_" -ForegroundColor Red
    Write-Host "Deploy interrompido." -ForegroundColor Red
    Write-Host ""
    exit 1
}