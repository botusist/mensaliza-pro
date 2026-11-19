# Mensaliza Pro

MVP operacional de cobrança recorrente multiempresa em Laravel 13. Valores monetários são armazenados em centavos; vencimentos são datas locais e eventos são timestamps. O banco aplica unicidade por empresa, contrato e competência.

## Executar localmente

```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

O seed cria exclusivamente dados fictícios para a empresa “Instituto Horizonte”. A aplicação usa SQLite localmente; configure MySQL e Redis pelo `.env` em produção.

## Estado funcional

| Área | Estado |
|---|---|
| Dashboard e indicadores por saldo | Concluído |
| Clientes e responsáveis | Cadastro/listagem funcional |
| Planos e contratos | Modelo e listagem funcional |
| Cobranças e pagamentos parciais | Funcional, com auditoria |
| Idempotência de competência e outbox | Constraints implementadas |
| Tema responsivo e estados financeiros | Concluído |
| Automa WhatsApp | Adaptador real pendente de documentação/credenciais |
| Asaas | Pendente de credenciais; desativado |
| Portal, acordos, CSV/PDF e autenticação completa | Próximas etapas |

## Produção

Use Nginx + PHP-FPM, MySQL e Redis. Configure um único scheduler (`* * * * * php /CAMINHO/artisan schedule:run`) e Supervisor para `php artisan queue:work redis --queue=webhooks,charges,notifications --tries=5 --backoff=10`. Faça backup antes do deploy, rode `php artisan migrate --force`, compile assets e reinicie workers com `php artisan queue:restart`.

Integrações externas permanecem desligadas até contrato oficial e credenciais válidas. Nenhum seed, teste ou build envia WhatsApp ou cria cobranças bancárias.
