# Instalação no aaPanel

Domínio de produção: `menasaliza.botusist.net.br`.

## Preparação única no painel

1. Aponte o registro DNS `A` do subdomínio para o IP da VPS.
2. Instale Nginx, MySQL, Redis e PHP 8.3 no App Store. No PHP, habilite `bcmath`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `redis`, `tokenizer` e `xml`.
3. Em **Website > PHP Project > Create from Git**, use `https://github.com/botusist/mensaliza-pro.git`, branch `main`, domínio `menasaliza.botusist.net.br`, path `/www/wwwroot/menasaliza.botusist.net.br` e PHP 8.3.
4. Crie um banco MySQL e usuário exclusivo no aaPanel.
5. Defina o **Running directory** como `/public` e solicite SSL. Ative Force HTTPS somente após o certificado ser emitido.

## Instalação automática

No Terminal do aaPanel execute:

```bash
cd /www/wwwroot/menasaliza.botusist.net.br
sudo bash deploy/aapanel/install.sh
```

O instalador solicitará apenas banco e, opcionalmente, SMTP. Ele instala dependências, gera a chave, configura produção, executa migrations, ajusta permissões e ativa scheduler e worker.

Não execute o instalador por uma rota pública. Credenciais devem permanecer somente no `.env` com permissão `600`.

## Atualização

```bash
cd /www/wwwroot/menasaliza.botusist.net.br
sudo -u www git pull --ff-only
sudo -u www composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -u www /www/server/php/83/bin/php artisan migrate --force
sudo -u www /www/server/php/83/bin/php artisan optimize
sudo /www/server/php/83/bin/php artisan queue:restart
```

Antes de atualizar, use os recursos de backup de site e banco do aaPanel.
