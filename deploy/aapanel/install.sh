#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

DOMAIN="menasaliza.botusist.net.br"
APP_DIR="/www/wwwroot/${DOMAIN}"
PHP_BIN="/www/server/php/83/bin/php"
APP_USER="www"

fail(){ printf 'ERRO: %s\n' "$1" >&2; exit 1; }
info(){ printf '\n==> %s\n' "$1"; }
ask(){ local prompt="$1" var="$2" secret="${3:-no}" value; if [[ "$secret" == yes ]]; then read -r -s -p "$prompt: " value; printf '\n'; else read -r -p "$prompt: " value; fi; [[ -n "$value" ]] || fail "$prompt é obrigatório"; printf -v "$var" '%s' "$value"; }

[[ "${EUID}" -eq 0 ]] || fail "execute como root pelo Terminal do aaPanel"
[[ "$APP_DIR" == /www/wwwroot/* ]] || fail "diretório fora de /www/wwwroot"
[[ -f "${APP_DIR}/artisan" ]] || fail "clone o projeto em ${APP_DIR} usando Website > PHP Project > Create from Git"
[[ -x "$PHP_BIN" ]] || PHP_BIN="$(command -v php || true)"
[[ -x "$PHP_BIN" ]] || fail "PHP CLI não encontrado; instale PHP 8.3 no App Store e selecione-o em PHP CLI"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION,"8.3.0",">=")?0:1);' || fail "PHP 8.3 ou superior é obrigatório"
command -v composer >/dev/null || fail "Composer não encontrado; instale-o pelo aaPanel"

printf '\nMensaliza Pro — instalação aaPanel\nDomínio: %s\nDiretório: %s\n\n' "$DOMAIN" "$APP_DIR"
ask "Banco MySQL" DB_NAME
ask "Usuário MySQL" DB_USER
ask "Senha MySQL" DB_PASS yes
read -r -p "Host MySQL [127.0.0.1]: " DB_HOST; DB_HOST="${DB_HOST:-127.0.0.1}"
read -r -p "Porta MySQL [3306]: " DB_PORT; DB_PORT="${DB_PORT:-3306}"
read -r -p "Host Redis [127.0.0.1]: " REDIS_HOST; REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
read -r -p "Configurar SMTP agora? [s/N]: " SMTP_YN
MAIL_MAILER="log"; MAIL_HOST="127.0.0.1"; MAIL_PORT="587"; MAIL_USER=""; MAIL_PASS=""; MAIL_SCHEME="tls"; MAIL_FROM="nao-responda@${DOMAIN}"
if [[ "$SMTP_YN" =~ ^[sS]$ ]]; then
  MAIL_MAILER="smtp"; ask "Host SMTP" MAIL_HOST; read -r -p "Porta SMTP [587]: " MAIL_PORT; MAIL_PORT="${MAIL_PORT:-587}"; ask "Usuário SMTP" MAIL_USER; ask "Senha SMTP" MAIL_PASS yes; read -r -p "E-mail remetente [${MAIL_FROM}]: " FROM_INPUT; MAIL_FROM="${FROM_INPUT:-$MAIL_FROM}"
fi

cd "$APP_DIR"
info "Instalando dependências PHP"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
  cp .env.production.example .env
  "$PHP_BIN" artisan key:generate --force --no-interaction
fi

info "Gravando configuração de produção"
"$PHP_BIN" deploy/aapanel/configure-env.php .env <<EOF
APP_URL=https://${DOMAIN}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
REDIS_HOST=${REDIS_HOST}
MAIL_MAILER=${MAIL_MAILER}
MAIL_SCHEME=${MAIL_SCHEME}
MAIL_HOST=${MAIL_HOST}
MAIL_PORT=${MAIL_PORT}
MAIL_USERNAME=${MAIL_USER}
MAIL_PASSWORD=${MAIL_PASS}
MAIL_FROM_ADDRESS=${MAIL_FROM}
EOF
chmod 600 .env

info "Validando banco e executando migrations"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan migrate --force --no-interaction
"$PHP_BIN" artisan storage:link --force 2>/dev/null || true
"$PHP_BIN" artisan optimize

info "Configurando permissões"
chown -R "${APP_USER}:${APP_USER}" "$APP_DIR"
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +

info "Instalando scheduler e worker systemd"
cat > /etc/cron.d/mensaliza-pro <<EOF
* * * * * ${APP_USER} cd ${APP_DIR} && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/mensaliza-pro
cat > /etc/systemd/system/mensaliza-worker.service <<EOF
[Unit]
Description=Mensaliza Pro Queue Worker
After=network.target mysql.service redis.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} artisan queue:work redis --queue=webhooks,charges,notifications,default --sleep=3 --tries=5 --timeout=60 --max-time=3600
Restart=always
RestartSec=5
TimeoutStopSec=90

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now mensaliza-worker.service

info "Verificação final"
"$PHP_BIN" artisan about --only=environment,cache,drivers
"$PHP_BIN" artisan schedule:list
systemctl is-active --quiet mensaliza-worker.service || fail "worker não iniciou; consulte journalctl -u mensaliza-worker"
printf '\nINSTALAÇÃO CONCLUÍDA\nAbra: https://%s\nHealth check: https://%s/up\n\nNo aaPanel confirme:\n- Running directory: /public\n- PHP: 8.3 ou superior\n- SSL: ativo com Force HTTPS\n- Extensões: bcmath, ctype, curl, fileinfo, intl, mbstring, openssl, pdo_mysql, redis, tokenizer, xml\n' "$DOMAIN" "$DOMAIN"
