# Status do Projeto

Atualizado em: 2026-09-17

## Etapa Atual

Proxima etapa recomendada: **Etapa 6 — Scheduler, filas e cache**.

## Etapas

| Etapa | Status | Observacao |
| --- | --- | --- |
| 0 — Diagnostico e planejamento | Concluida | Ambiente inspecionado e arquitetura inicial documentada. |
| 1 — Bootstrap do projeto | Concluida | Laravel 12 instalado, executavel, testado e configurado para MySQL via `.env.example`. |
| 2 — Dominio, migrations e models | Concluida | Schema, models, factories, seeder e testes criados. |
| 2.1 — Escopo de transmissao e publicacao | Concluida | Dominio adaptado para TheSportsDB, OpenAI fallback, revisao e publicacao manual. |
| 3 — Provedor API-Football | Concluida | Contrato, DTOs, provider HTTP, comando de diagnostico e testes fake criados. |
| 4 — Sincronizacao idempotente | Concluida | `football:sync` persiste competicoes, times e partidas sem duplicar registros. |
| 5 — Resolver transmissoes com TheSportsDB | Concluida | `football:resolve-broadcasts` pesquisa transmissoes na TheSportsDB e grava fontes/transmissoes em rascunho. |
| 5.1 — Interface publica | Pendente | Aguardando dados publicados. |
| 6 — Scheduler, filas e cache | Pendente | Proximo passo para automatizar sincronizacao e resolucao. |
| 7 — OpenAI para transmissoes ausentes | Concluida | `football:resolve-openai-broadcasts` usa Responses API com web search e schema estruturado. |
| 8 — Painel administrativo | Pendente | Aguardando dados e revisao. |
| 9 — Endurecimento do MVP | Pendente | Aguardando MVP funcional. |
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
- Etapa 7: `php artisan football:resolve-openai-broadcasts --from=YYYY-MM-DD --to=YYYY-MM-DD --limit=10`.

## Decisoes Permanentes

- `APP_TIMEZONE=America/Sao_Paulo` em `.env.example`.
- `DB_CONNECTION=mysql` em `.env.example`, sem credenciais reais.
- MySQL e o banco padrao para desenvolvimento/producao. O `.env` local deve apontar para um banco/usuario reais.
- Chaves vazias adicionadas para API-Football, TheSportsDB e OpenAI.
- `AGENTS.md` documenta os comandos operacionais do projeto.
- Dominio persistente criado com `competitions`, `teams`, `football_fixtures`, `broadcasters` e `fixture_broadcasts`.
- `confidence` de transmissao e decimal de 0 a 1, alinhado ao uso futuro com OpenAI.
- Estados independentes adicionados para resolucao, revisao e publicacao.
- Historico de evidencias separado em `broadcast_sources`.
- Mapeamento de IDs externos separado em `fixture_provider_mappings`.
- Modo de publicacao persistente padrao: `manual`.
- API-Football fica atras de `FootballDataProvider`.
- A Etapa 3 normaliza dados em DTOs, sem persistir no banco.
- `FixtureSynchronizer` persiste competicoes, times e partidas com `updateOrCreate`.
- `football:sync` ainda nao consulta TheSportsDB nem OpenAI; transmissoes sao resolvidas em comando separado.
- TheSportsDB fica atras de `BroadcastFinder`.
- `BroadcastResolver` persiste `broadcast_sources`, `broadcasters`, `fixture_broadcasts` e `fixture_provider_mappings`.
- Transmissoes encontradas automaticamente continuam com `review_status=pending` e `publication_status=draft`.
- OpenAI e usada apenas por comando de fallback para jogos nao resolvidos pela TheSportsDB.
- `OpenAIBroadcastFinder` usa Responses API, web search e JSON Schema estruturado.

## Pendencias Reais

- Manter `.env` local com credenciais reais fora do Git.
- Implementar Scheduler, filas e cache para automatizar o fluxo.
- Ainda nao ha Scheduler ou painel administrativo.
