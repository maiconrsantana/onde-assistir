# Status do Projeto

Atualizado em: 2026-09-19

## Etapa Atual

Etapa atual: **Etapa 9 — Endurecimento do MVP**.

## Etapas

| Etapa | Status | Observacao |
| --- | --- | --- |
| 0 — Diagnostico e planejamento | Concluida | Ambiente inspecionado e arquitetura inicial documentada. |
| 1 — Bootstrap do projeto | Concluida | Laravel 12 instalado, executavel, testado e configurado para MySQL via `.env.example`. |
| 2 — Dominio, migrations e models | Concluida | Schema, models, factories, seeder e testes criados. |
| 2.1 — Escopo de transmissao e publicacao | Concluida | Dominio adaptado para TheSportsDB, OpenAI fallback, revisao e publicacao manual. |
| 3 — Provedor esportivo | Concluida | Contrato, DTOs, provider HTTP do football-data.org, comando de diagnostico e testes fake criados. |
| 4 — Sincronizacao idempotente | Concluida | `football:sync` persiste competicoes, times e partidas sem duplicar registros. |
| 5 — Resolver transmissoes com TheSportsDB | Concluida | `football:resolve-broadcasts` pesquisa transmissoes na TheSportsDB e grava fontes/transmissoes em rascunho. |
| 5.1 — Interface publica | Concluida | Rota `/` exibe agenda publica somente com jogos publicados e aprovados. |
| 6 — Scheduler, filas e cache | Concluida | Scheduler registrado, status operacional em cache e invalidação do cache publico em sucesso. |
| 7 — OpenAI para transmissoes ausentes | Concluida | `football:resolve-openai-broadcasts` usa Responses API com web search e schema estruturado. |
| 8 — Painel administrativo | Concluida | Filament instalado em `/admin`, com recursos para partidas, emissoras e transmissoes. |
| 9 — Endurecimento do MVP | Em andamento | Testes de endurecimento e API publica versionada iniciados, preparando futuros clientes Android/iOS. |
| 10 — SEO, docs e deploy | Pendente | Aguardando MVP funcional. |
| 11 — Libertadores | Futuro | Somente apos Brasileirão estabilizado. |

## Evidencias da Validacao

- PHP: 8.3.33.
- Composer: 2.9.5.
- Laravel: 12.69.2.
- Node local: 22.23.2.
- npm local: 10.9.8.
- Testes: `php artisan test`.
- Build frontend: `source .node-env && npm run build`.
- Etapa 2: `php artisan migrate:fresh --seed`.
- Etapa 3: `php artisan football:probe-provider --from=YYYY-MM-DD --to=YYYY-MM-DD`.
- Etapa 4: `php artisan football:sync --from=YYYY-MM-DD --to=YYYY-MM-DD`.
- Etapa 5: `php artisan football:resolve-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD`.
- Etapa 6: `CACHE_STORE=array php artisan schedule:list`.
- Etapa 7: `php artisan football:resolve-openai-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD --limit=10`.
- Etapa 8: `/admin`.
- Etapa 5.1: `/`.

## Decisoes Permanentes

- `APP_TIMEZONE=America/Sao_Paulo` em `.env.example`.
- `DB_CONNECTION=mysql` em `.env.example`, sem credenciais reais.
- MySQL e o banco padrao para desenvolvimento/producao. O `.env` local deve apontar para um banco/usuario reais.
- Chaves vazias adicionadas para football-data.org, TheSportsDB e OpenAI.
- `AGENTS.md` documenta os comandos operacionais do projeto.
- Dominio persistente criado com `competitions`, `teams`, `football_fixtures`, `broadcasters` e `fixture_broadcasts`.
- `confidence` de transmissao e decimal de 0 a 1, alinhado ao uso futuro com OpenAI.
- Estados independentes adicionados para resolucao, revisao e publicacao.
- Historico de evidencias separado em `broadcast_sources`.
- Mapeamento de IDs externos separado em `fixture_provider_mappings`.
- Modo de publicacao persistente padrao: `manual`.
- football-data.org fica atras de `FootballDataProvider`; API-Football permanece apenas como adaptador legado testado.
- A Etapa 3 normaliza dados em DTOs, sem persistir no banco.
- `FixtureSynchronizer` persiste competicoes, times e partidas com `updateOrCreate`.
- `football:sync` ainda nao consulta TheSportsDB nem OpenAI; transmissoes sao resolvidas em comando separado.
- TheSportsDB fica atras de `BroadcastFinder`.
- `BroadcastResolver` persiste `broadcast_sources`, `broadcasters`, `fixture_broadcasts` e `fixture_provider_mappings`.
- Transmissoes encontradas automaticamente continuam com `review_status=pending` e `publication_status=draft`.
- OpenAI e usada apenas por comando de fallback para jogos nao resolvidos pela TheSportsDB.
- `OpenAIBroadcastFinder` usa Responses API, web search e JSON Schema estruturado.
- Scheduler roda `football:sync --days=14` as 06:00 e `football:refresh-broadcasts --days=7` as 16:00 em `America/Sao_Paulo`.
- `football:automation-status` le status operacional leve salvo em cache.
- Cache publico atual e invalidado somente quando sincronizacao/resolucao termina com sucesso.
- Filament 5 fica em `/admin`; usuarios precisam de `is_admin=true` para acessar.
- A acao de partidas no Filament aprova e publica a partida, alem de invalidar o cache publico.
- A acao de transmissoes no Filament aprova apenas a transmissao e invalida o cache publico.
- Alteracoes manuais de transmissao gravam `broadcast_sources.provider=manual`.
- Transmissoes manuais nao sao sobrescritas por resolucoes automaticas futuras.
- A rota publica `/` nao chama APIs externas durante a request do visitante.
- A agenda publica lista apenas partidas com `publication_status=published` e `review_status=approved`.
- Transmissoes publicas sao filtradas por Brasil e precisam estar sem `needs_review`, aprovadas e publicadas; o painel permite publicar ou retirar cada transmissao individualmente.
- Jogos publicados sem transmissao publicavel exibem "Transmissao ainda nao divulgada".
- API publica versionada criada em `/api/v1/fixtures` para futuros clientes mobile, com filtros, paginação, rate limit e contrato JSON sem `raw_payload`.
- Comandos automaticos registram inicio, fim, duracao, totais e falhas no log; o dashboard do Filament sinaliza automacoes desatualizadas ou com erro.
- URLs de fontes passam por validacao central para aceitar somente `http` e `https`; o fluxo integrado provider -> sincronizador -> banco -> pagina/API esta coberto por teste.
- Retencao de payloads implementada com `football:prune-raw-data`, simulacao padrao e execucao explicita; logs diarios suportam retencao configuravel por `LOG_DAILY_DAYS`.
- O health check operacional usa a rota nativa `/up` do Laravel e esta coberto por teste automatizado.

## Pendencias Reais

- Manter `.env` local com credenciais reais fora do Git.
- Endurecer o MVP com filtros, SEO, estados de erro/vazio e revisao visual em dados reais.
- Concluir a revisão visual com dados reais e decidir a evolução dos filtros da API para consultas diretamente no banco em volumes maiores.
