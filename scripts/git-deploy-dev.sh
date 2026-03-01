#!/bin/bash
set -e

if [ -z "$1" ]; then
    echo -e "\033[0;31mErro: Informe a mensagem do commit.\033[0m"
    echo "Uso: git-deploy-dev \"descricao do commit\""
    exit 1
fi

MSG="$1"

echo ""
echo -e "\033[1;33m== Git Deploy - develop ==\033[0m"
echo ""

echo -e "\033[0;32m[1/3] Commit na develop...\033[0m"
git add .
git commit -m "$MSG"

echo -e "\033[0;32m[2/3] Push origin...\033[0m"
git push

echo -e "\033[0;32m[3/3] Push github...\033[0m"
git push github

echo ""
echo -e "\033[0;32mDeploy develop concluido com sucesso!\033[0m"
echo ""