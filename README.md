# Onde Passa o Jogo

Aplicacao Laravel 12 para listar partidas, horarios e transmissoes do Campeonato Brasileiro no Brasil.

## Ambiente

- PHP 8.3+
- Composer
- MySQL
- Node local via `.node-env`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
source .node-env
npm install
php artisan migrate:fresh --seed
```

Configure as credenciais reais apenas no `.env` local:

```env
API_FOOTBALL_KEY=
THESPORTSDB_API_KEY=
OPENAI_API_KEY=
```

## Comandos

```bash
php artisan football:sync --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan football:resolve-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan football:resolve-openai-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD --limit=10
php artisan football:refresh-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan football:automation-status
```

## Scheduler

O Scheduler esta registrado em `routes/console.php`:

- 06:00 `America/Sao_Paulo`: `football:sync --days=14`
- 16:00 `America/Sao_Paulo`: `football:refresh-broadcasts --days=7`

No servidor, configure um unico cron para o Laravel:

```bash
* * * * * cd /var/www/onde-assistir && php artisan schedule:run >> /dev/null 2>&1
```

Os eventos usam `withoutOverlapping`. Como o cache padrao do projeto usa `database`, rode as migrations de cache/jobs antes de depender do Scheduler em ambiente real.

## Admin

O painel administrativo fica em `/admin` e usa autenticação Laravel/Filament. Não existe credencial padrão no código.

Para habilitar acesso, crie um usuário e marque `is_admin=true` no banco:

```bash
php artisan make:filament-user
```

Depois ajuste o campo `is_admin` desse usuário para `1` no MySQL.

## Interface publica

A rota `/` exibe a agenda publica. Ela le apenas dados locais publicados e aprovados, sem consultar API-Football, TheSportsDB ou OpenAI durante a request do visitante.

Jogos sem transmissao publicavel exibem `Transmissao ainda nao divulgada`.

## Validacao

```bash
./vendor/bin/pint
php artisan test
source .node-env && npm run build
CACHE_STORE=array php artisan schedule:list
```

## Publicacao

O modo padrao e manual. Transmissoes encontradas automaticamente ficam em rascunho ate revisao/aprovacao futura no painel administrativo.
