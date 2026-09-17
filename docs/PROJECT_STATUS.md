# Status do Projeto

Atualizado em: 2026-09-16

## Etapa Atual

Proxima etapa recomendada: **Etapa 4 — sincronizacao idempotente dos jogos**.

## Etapas

| Etapa | Status | Observacao |
| --- | --- | --- |
| 0 — Diagnostico e planejamento | Concluida | Ambiente inspecionado e arquitetura inicial documentada. |
| 1 — Bootstrap do projeto | Concluida | Laravel 12 instalado, executavel, testado e configurado para MySQL via `.env.example`. |
| 2 — Dominio, migrations e models | Concluida | Schema, models, factories, seeder e testes criados. |
| 2.1 — Escopo de transmissao e publicacao | Concluida | Dominio adaptado para TheSportsDB, OpenAI fallback, revisao e publicacao manual. |
| 3 — Provedor API-Football | Concluida | Contrato, DTOs, provider HTTP, comando de diagnostico e testes fake criados. |
| 4 — Sincronizacao idempotente | Pendente | Proximo passo. |
| 5 — Interface publica | Pendente | Aguardando dados locais. |
| 6 — Scheduler, filas e cache | Pendente | Aguardando sincronizacao. |
| 7 — OpenAI para transmissoes ausentes | Pendente | Aguardando fluxo base. |
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

## Pendencias Reais

- Criar banco MySQL `onde_assistir` e preencher `.env` local com usuario/senha reais fora do Git.
- Implementar sincronizador idempotente para persistir jogos vindos do `FootballDataProvider`.
- Implementar TheSportsDB antes de OpenAI no fluxo de transmissao.
- Ainda nao ha integracao com API-Football, TheSportsDB, OpenAI, Scheduler ou painel administrativo.
