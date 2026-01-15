# simp20-php
## Build / Deploy

PARA PUBLICAR EM LOCALHOST (VDESK)

é necessário preencher corretamente o arquivo "docker/.env"
```
sudo mkdir -p /nfs/swarm/simp20-php/uploads

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

PARA PUBLICAR EM HOMOLOGAÇÃO

. é necessário estar na branch STAGING
```
git checkout staging
git add .
git commit -m "XPTO"
git push
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
git add .
git commit -m "descrição"
git push
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
https://nfs.cesan.com.br/simp20-php/files/uploads/
usuário: cesan
senha: a6BLRCPJ2yefub