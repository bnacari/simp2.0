#!/bin/bash
set -e

if [ -z "$1" ]; then
    echo -e "\033[0;31mErro: Informe a mensagem do commit.\033[0m"
    echo "Uso: git-deploy-prd \"descricao do commit\""
    exit 1
fi

MSG="$1"

echo ""
echo -e "\033[1;33m== Git Deploy - develop > master ==\033[0m"
echo ""

echo -e "\033[0;32m[1/5] Commit na develop...\033[0m"
git add .
git commit -m "$MSG"

echo -e "\033[0;32m[2/5] Push da develop (origin + github)...\033[0m"
git push
git push github

echo -e "\033[0;32m[3/5] Checkout master e merge develop...\033[0m"
git checkout master
git merge develop

echo -e "\033[0;32m[4/5] Push da master (origin + github)...\033[0m"
git push
git push github

echo -e "\033[0;32m[5/5] Voltando para develop...\033[0m"
git checkout develop

echo ""
echo -e "\033[0;32mDeploy concluido com sucesso!\033[0m"
echo ""