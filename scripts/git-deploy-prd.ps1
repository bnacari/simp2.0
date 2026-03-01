param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$Msg
)

$ErrorActionPreference = "Stop"

function Write-Step($step, $text) { Write-Host "[$step] $text" -ForegroundColor Green }
function Write-Title($text) { Write-Host $text -ForegroundColor Yellow }

Write-Host ""
Write-Title "== Git Deploy - develop > master =="
Write-Host ""

try {
    Write-Step "1/5" "Commit na develop..."
    git add .
    if ($LASTEXITCODE -ne 0) { throw "Falha no git add" }

    git commit -m $Msg
    if ($LASTEXITCODE -ne 0) { throw "Falha no git commit" }

    Write-Step "2/5" "Push da develop (origin + github)..."
    git push
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push origin" }

    git push github
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push github" }

    Write-Step "3/5" "Checkout master e merge develop..."
    git checkout master
    if ($LASTEXITCODE -ne 0) { throw "Falha no checkout master" }

    git merge develop
    if ($LASTEXITCODE -ne 0) { throw "Falha no merge develop" }

    Write-Step "4/5" "Push da master (origin + github)..."
    git push
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push origin" }

    git push github
    if ($LASTEXITCODE -ne 0) { throw "Falha no git push github" }

    Write-Step "5/5" "Voltando para develop..."
    git checkout develop
    if ($LASTEXITCODE -ne 0) { throw "Falha no checkout develop" }

    Write-Host ""
    Write-Host "Deploy concluido com sucesso!" -ForegroundColor Green
    Write-Host ""
}
catch {
    Write-Host ""
    Write-Host "Erro: $_" -ForegroundColor Red
    Write-Host "Deploy interrompido." -ForegroundColor Red
    Write-Host ""
    exit 1
}