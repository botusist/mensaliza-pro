# Memória de continuidade — Mensaliza Pro

Atualizado em 19/11/2026. Este arquivo é o ponto de retomada para a próxima sessão.

## Repositório e ambiente

- Repositório: `https://github.com/botusist/mensaliza-pro.git`
- Branch principal: `main`
- Diretório local: `D:\Tela2\mensaliza`
- Stack: Laravel 13.43, PHP 8.5 local, Blade, Tailwind CSS 4, Vite, SQLite local.
- Produção planejada: aaPanel, Nginx, PHP-FPM 8.3+, MySQL e Redis.
- Domínio informado pelo usuário: `menasaliza.botusist.net.br` (manter essa grafia).
- Locale: `pt_BR`.
- Timezone: `America/Sao_Paulo`.

## Executar localmente

```powershell
composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
php artisan schedule:work
php artisan queue:work database --queue=notifications,default --tries=5 --timeout=30
```

URL local: `http://127.0.0.1:8000`.

Os processos locais iniciados em uma sessão anterior podem não continuar ativos após reinício. Verificar e iniciar novamente quando necessário.

## Funcionalidades concluídas

- Dashboard financeiro responsivo.
- Tema claro/escuro persistido no navegador.
- SaaS multiempresa com `tenant_id` e central do superadmin.
- CRUD seguro de empresas; exclusão funciona como suspensão.
- Planos comerciais da plataforma separados dos planos de mensalidade.
- CRUD de clientes; exclusão funciona como inativação.
- CRUD de contratos; exclusão funciona como cancelamento e preserva cobranças.
- CRUD de cobranças; cobrança com pagamento não pode ser apagada.
- Recebimentos manuais e parciais em centavos inteiros.
- Auditoria de alterações financeiras e administrativas.
- Regra de vencimento para dias 29, 30 e 31.
- Unicidade de cobrança por empresa, contrato e competência.
- Portal do cliente com login, logout, mensalidades e recuperação de senha.
- Criação/redefinição do acesso do cliente pela ficha ou listagem.
- Integração Automa por empresa com segredo criptografado.
- Endpoint Automa documentado: `POST https://automa.botusist.net.br/api/send/whatsapp`.
- Régua automática: 5 e 2 dias antes, no vencimento, 3, 7 e 15 dias depois.
- Outbox idempotente para WhatsApp e e-mail.
- Jobs revalidam saldo, status e consentimento antes do envio.
- HTTP 200 do Automa é `accepted_unconfirmed`, nunca confirmação de entrega.
- Meses e mensagens em português do Brasil.
- Preparação para aaPanel e instalador de um comando.

## Pontos operacionais importantes

- Os clientes do seed são fictícios. Telefones devem ser substituídos por números reais no formato aceito pelo Automa, com DDI, DDD e número. Exemplo visual: `5511999999999`.
- Não acrescentar `55` automaticamente; o sistema não deve presumir país ou alterar números internacionais.
- A ocorrência automática testada para Eduarda Lima usou o número fictício `11920906048`; o Automa aceitou a requisição, mas não confirmou entrega.
- A integração Automa real foi testada pelo usuário e funcionou no envio manual.
- Não registrar ou versionar `secret`, credenciais SMTP, `.env`, banco SQLite ou logs.
- SMTP local continua com `MAIL_MAILER=log` até existirem credenciais reais.
- Para SMTP em produção, configurar o `.env`, executar `php artisan config:clear` e `php artisan queue:restart`.
- Cobranças canceladas, contratos cancelados e clientes inativos preservam histórico.
- Não reenviar automaticamente resultados desconhecidos ou aceitos sem confirmação.

## Implantação aaPanel

Documentação: `deploy/aapanel/README.md`.

Depois de criar o PHP Project pelo Git no aaPanel:

```bash
cd /www/wwwroot/menasaliza.botusist.net.br
sudo bash deploy/aapanel/install.sh
```

Configuração do site:

- PHP 8.3 ou superior.
- Running directory: `/public`.
- SSL e Force HTTPS pelo aaPanel.
- Extensões: bcmath, ctype, curl, fileinfo, intl, mbstring, openssl, pdo_mysql, redis, tokenizer e xml.

O instalador configura `.env`, dependências, migrations, caches, permissões, cron e worker systemd. Assets de produção estão versionados para não exigir Node.js na VPS.

## Verificações conhecidas

- Última suíte completa: 15 testes, 42 asserções, todos aprovados.
- Build Vite aprovado.
- Pint aprovado.
- Rotas principais verificadas com HTTP 200.

Ao retomar, execute:

```powershell
git status --short --branch
git pull --ff-only
php artisan test
npm run build
```

## Próximos passos recomendados

1. Implementar autenticação e autorização completa para equipe administrativa; atualmente o portal do cliente possui autenticação, mas as telas administrativas ainda precisam de middleware e policies por função.
2. Remover o tenant fixo `1` do `AppController` e resolver a empresa pela sessão do usuário autenticado.
3. Proteger a central do superadmin com middleware exclusivo de `role=superadmin`.
4. Adicionar CRUD completo dos planos de mensalidade e dos planos comerciais da plataforma.
5. Adicionar estorno por lançamento compensatório, acordos e renegociações.
6. Adicionar recibos PDF, exportações CSV/PDF e portal com downloads privados.
7. Melhorar histórico das mensagens de e-mail na tela de automações.
8. Configurar SMTP real e testar recuperação de senha e lembretes em sandbox/controlado.
9. Implantar no aaPanel, validar DNS/SSL, scheduler, Redis, workers, backup e restauração.
10. Fazer inspeção visual em desktop e mobile após a implantação.

## Regra de retomada

Antes de qualquer mudança, ler este arquivo, executar os testes e verificar o estado do Git. Preservar as decisões de segurança financeira, isolamento multiempresa, idempotência e ausência de reenvio cego.
