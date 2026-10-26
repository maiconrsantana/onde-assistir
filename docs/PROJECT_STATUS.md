# Status do Projeto

Atualizado em: 2026-10-22

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
| 9 — Endurecimento do MVP | Em andamento | API publica integrada ao frontend React na raiz `/`; automacoes centralizadas, controles no painel e logs de execucao implementados. |
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
- Frontend React: `cd frontend && npm run build`.
- Frontend publicado na raiz `/` servido pelo Apache a partir de `public`; `public/app` permanece como compatibilidade legada.
- API consumida pelo React: `/api/v1/fixtures`.
- Suíte atual: `php artisan test` — 80 testes, 330 assertions.
- Base de automacoes: `php artisan migrate` criou `automation_settings` e `automation_runs`.
- Runner de automacoes: `App\\Services\\Automation\\AutomationRunner`.
- Dispatcher central: `App\\Services\\Automation\\AutomationDispatcher`, usado pelo painel e pelo Scheduler.
- Painel de automacoes: `/admin/automation-settings` permite habilitar, desabilitar e executar cada rotina manualmente.
- Logs de automacoes: `/admin/automation-runs` lista status, disparo, duracao, totais, usuario e erros.
- Testes atuais: `php artisan test` — 80 testes, 330 assertions.

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
- Gemini e OpenAI ficam disponiveis como fallback configuravel para jogos nao resolvidos pela TheSportsDB.
- `GeminiBroadcastFinder` e `OpenAIBroadcastFinder` implementam `AiBroadcastFinder` e normalizam respostas no DTO `BroadcastSearchResult`.
- `AI_BROADCAST_PROVIDER=gemini` ativa Gemini; `AI_BROADCAST_PROVIDER=openai` ativa OpenAI.
- Scheduler roda a cada minuto em `America/Sao_Paulo`, verifica dias e horarios configurados no painel e apenas enfileira as automacoes devidas.
- `football:automation-status` le status operacional leve salvo em cache.
- Cache publico atual e invalidado somente quando sincronizacao/resolucao termina com sucesso.
- `PublicScheduleCacheObserver` invalida o cache automaticamente ao salvar ou excluir partidas e transmissoes, inclusive em edicoes manuais do painel.
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
- Frontend React incorporado em `frontend/`, compilado como arquivos estáticos e publicado em `/`, sem `npm` em tempo de execução do Apache; `/api`, `/admin` e `/up` continuam no Laravel.
- O React consome somente a API própria Laravel, usando `REACT_APP_API_BASE_URL`; provedores esportivos e serviços de IA permanecem no backend.
- O front React alterna entre jogos anteriores e próximos, envia intervalos `from`/`to` e exibe partidas finalizadas; a API também aceita `date` para consultar dias passados.
- O contrato da API fornece horários UTC/Brasília, status, escudos (`crest`), estádio/cidade e transmissões publicadas.
- CORS é configurável por `CORS_ALLOWED_ORIGINS`; a publicação no mesmo domínio reduz a necessidade de CORS em produção.
- A busca de partidas no painel pesquisa ID externo, nome e abreviação dos dois times.
- Automacoes possuem configuracao persistida, habilitacao individual, tipo de disparo, lock por operacao e registro de execucao.
- `AutomationRunner` registra execucoes como `running`, `success`, `failed` ou `skipped`, com parametros, duracao, totais e usuario responsavel.
- Execucoes desabilitadas ou concorrentes sao registradas como `skipped`, sem chamar a tarefa externa.
- O resultado resumido da tarefa e armazenado em `automation_runs.result` para auditoria no painel.
- Resolucao sem transmissao registra no resultado a justificativa da fonte; o painel exibe a saida detalhada da chamada na coluna opcional `Detalhes da chamada`.
- Painel de partidas permite alterar `resolution_status` em lote sem modificar revisao/publicacao.
- Painel oferece `Buscar transmissao via IA` por partida, usando o provedor configurado e registrando a execucao individual.
- Comandos automaticos registram inicio, fim, duracao, totais e falhas no log; o dashboard do Filament sinaliza automacoes desatualizadas ou com erro.
- URLs de fontes passam por validacao central para aceitar somente `http` e `https`; o fluxo integrado provider -> sincronizador -> banco -> pagina/API esta coberto por teste.
- Retencao de payloads implementada com `football:prune-raw-data`, simulacao padrao e execucao explicita; logs diarios suportam retencao configuravel por `LOG_DAILY_DAYS`.
- O health check operacional usa a rota nativa `/up` do Laravel e esta coberto por teste automatizado.
- Gemini integrado via `generateContent` com Google Search grounding, JSON estruturado e evidencias normalizadas.

## Pendencias Reais

- Manter `.env` local com credenciais reais fora do Git.
- Concluir a revisão visual do React e da página Blade com dados reais em mobile e desktop.
- Evoluir os filtros de `/api/v1/fixtures` para consultas diretamente no banco quando o volume de partidas crescer; hoje o intervalo de até 30 dias é filtrado após a leitura do cache.
- Decidir e implementar a desativação completa do fallback Gemini/OpenAI caso o projeto permaneça somente com fontes manuais e TheSportsDB.
- Manter um worker ativo com `php artisan queue:work database --queue=automations --tries=1 --timeout=900`; execuções do painel e do Scheduler agora são assíncronas.
- Notificações persistentes do Filament informam o usuário quando uma automação enfileirada termina ou falha.
- Consumo de OpenAI/Gemini é normalizado em `llm_usage_records`, com tokens, modelo, partida, custo estimado e histórico no painel.
- Limites diário, mensal e por chamada ficam em `/admin/llm-budgets`; chamadas são bloqueadas antes do request quando o orçamento não comporta a reserva.
- Preços por milhão de tokens são configurados por `OPENAI_INPUT_COST_PER_MILLION`, `OPENAI_OUTPUT_COST_PER_MILLION`, `GEMINI_INPUT_COST_PER_MILLION` e `GEMINI_OUTPUT_COST_PER_MILLION`.
- Dias da semana e horário de cada automação são editáveis em `/admin/automation-settings`; agendamentos vazios ficam somente manuais.
- Integrar saldo real de créditos dos provedores quando houver endpoint confiável; por enquanto o painel controla limites internos e custo estimado.
- Executar a Etapa 10: README final, SEO, Open Graph, sitemap/robots quando aplicável e checklist de deploy.
