# simp20-php
## Build / Deploy

AGORA O DEPLOY É EXECUTADO VIA COMANDO
```
git-deploy-prd "descrição"
```
os comandos estão na pasta scripts do projeto

PARA PUBLICAR EM LOCALHOST (VDESK) TESTE

é necessário preencher corretamente o arquivo "docker/.env"
```
sudo mkdir -p /nfs/swarm/simp20-php/modelos_treinados

SUBIR AS VARIÁVEIS DE AMBIENTE 
source docker/.env

docker build -f docker/Dockerfile -t registry.cesan.com.br/cesan/simp20-php:0.0.1 .

docker push registry.cesan.com.br/cesan/simp20-php:0.0.1


SE FOR PRECISO CRIAR A NETWORK
docker network create --driver overlay --attachable backing-services_cntlm

SE FOR PRECISO EXCLUIR 
docker stack rm simp20-php 

SUBIR O CONTAINER
docker stack deploy --with-registry-auth -c docker/stackdev.yml $CI_PROJECT_NAME

```
<!-- container PYTHON necessário para testes do TENSORFLOW -->
# 1. Build do container TENSORFLOW
```

source docker/.env
docker login registry.cesan.com.br
docker build -f docker/Dockerfile.tensorflow -t registry.cesan.com.br/cesan/simp20-tensorflow:latest .
docker push registry.cesan.com.br/cesan/simp20-tensorflow:latest
docker service update --force simp20-php_simp20-tensorflow

# 2. Adicionar trechos nos stack.yml (ver arquivos _adicionar.yml)
# 3. Adicionar TENSORFLOW_URL=http://simp20-tensorflow:5000 no env do simp20-php
# 4. Redeploy
docker stack deploy --with-registry-auth -c docker/stack.yml simp20-php

```

PARA VERIFICAR AS CREDENCIAIS DE DENTRO DO CONTAINER

acessar o (https://portainer-swarm.sistemas.cesan.com.br/) e de dentro do container digitar. 
as variáveis devem ser as passadas no CICD do gitlab

echo "HOST=$DB_HOST | USER=$DB_USER | PASS=$DB_PASS | NAME=$DB_NAME"


```

PARA PUBLICAR EM HOMOLOGAÇÃO

. é necessário estar na branch STAGING
```
git checkout staging

git add . && git commit -m "descrição" && git push && git push github

```
PARA SUBIR A APLICAÇÃO EM PRODUÇÃO, APÓS REALIZAR AS ALTERAÇÕES NECESSÁRIAS, BASTA EXECUTAR:
Está com o CI/CD CONFIGURADO, basta seguir os passos abaixo.

. Se estiver na branch STAGING, é necessário:
```
git checkout master
git merge staging
```
. e seguir os passos abaixo:
```
git add . && git commit -m "descrição" && git push && git push github

```
<!-- NÃO ESTÁ FUNCIONANDO A INTEGRAÇÃO ABAIXO -->

IR ATÉ O SITE ABAIXO E REALIZAR O DEPLOY MANUALMENTE

https://gitlab-monitor.sistemas.cesan.com.br/

O deploy ocorre automaticamente quando há push nas branchs *staging* (homologação) ou *master* (produção).

```

```
ACESSAR O PORTAINER E TROCAR 'REPLICAS' PARA 1 PARA RODAR O SISTEMA MANUALMENTE
```
https://portainer-swarm.sistemas.cesan.com.br/#!/7/docker/stacks

```
ACESSAR A PASTA ONDE SALVO OS ARQUIVOS JSON
```
https://nfs.cesan.com.br/simp20-php
usuário: cesan
senha: a6BLRCPJ2yefub

https://hom-nfs.cesan.com.br/simp20-php
usuário: cesan
senha: a6BLRCPJ2yefub

https://devnfs.cesan.com.br/simp20-php
usuário: cesan
senha: a6BLRCPJ2yefub



```
ACESSAR O LINK DO DEEPSEEK QUE CONTROLA CRÉDITOS DA IA
```
https://platform.deepseek.com/usage