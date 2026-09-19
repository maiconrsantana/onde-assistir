# Arquitetura

## Estado Atual

O projeto esta em Laravel 12.69.2 com PHP 8.3.33, Composer 2.9.5, PHPUnit 11 e frontend Vite/Tailwind. O Node usado pelo projeto fica em `.tools/node-current` e e ativado com `source .node-env`.

O bootstrap do Laravel ja esta funcional via Apache em `https://maicon.receiv.it/` e tambem pode ser executado localmente com `php artisan serve`.

## Decisoes Base

- Backend: Laravel 12.
- Interface: Blade, Vite e Tailwind CSS.
- Banco alvo: MySQL para desenvolvimento/producao.
- Testes: devem ser executados contra banco isolado. Se forem executados em MySQL, use um database proprio de teste e nunca o banco de desenvolvimento/producao.
- Timezone da aplicacao: `America/Sao_Paulo`.
- Estrategia de datas futuras: persistir instantes de partidas em UTC e converter para `America/Sao_Paulo` na apresentacao.
- Provedor esportivo planejado: API-Football atras de uma interface.
- Provedor de transmissao planejado: TheSportsDB antes de qualquer fallback por IA.
- IA planejada: OpenAI Responses API apenas como fallback para transmissao ausente, conflitante ou vencida.
- Publicacao planejada: modo manual por padrao; pagina publica exibe somente registros publicados.

## Fluxo Planejado

1. Modelar competicoes, times, partidas, emissoras e transmissoes.
2. Buscar partidas via contrato de provedor esportivo API-Football.
3. Sincronizar os dados de forma idempotente.
4. Resolver transmissoes via TheSportsDB e, somente quando necessario, OpenAI.
5. Avaliar confiabilidade e manter historico de evidencias.
6. Revisar e publicar rodadas manualmente no Filament.
7. Exibir a agenda publica somente com dados persistidos e publicados.
8. Automatizar atualizacoes via Scheduler/jobs.

## Modelo de Dominio

### `competitions`

Representa campeonatos e temporadas importados de um provedor. `provider + external_id` e unico para permitir que a API-Football, ou outro provedor futuro, tenha seus proprios identificadores sem conflito global. `slug` e unico para URLs e filtros internos.

### `teams`

Representa clubes ou selecoes. Tambem usa `provider + external_id` como chave unica composta. `logo_url` e opcional porque a pagina publica nao deve depender de imagem remota para renderizar corretamente.

### `football_fixtures`

Representa uma partida. Relaciona campeonato, mandante e visitante. `starts_at` deve guardar o instante do jogo em UTC; a apresentacao converte para `America/Sao_Paulo`. `raw_payload` e opcional e deve ser usado apenas quando ajudar auditoria ou diagnostico da integracao.

Tambem guarda estados independentes do fluxo de transmissao:

- `resolution_status`: `pending`, `processing`, `resolved`, `not_found`, `uncertain`, `conflicting` ou `error`.
- `review_status`: `pending`, `approved` ou `rejected`.
- `publication_status`: `draft`, `published` ou `unpublished`.

Um jogo pode estar resolvido e ainda assim continuar em rascunho aguardando aprovacao humana.

### `broadcasters`

Catalogo de emissoras/plataformas. O campo `type` aceita `tv_open`, `tv_closed`, `streaming`, `youtube` ou `other`.

### `fixture_provider_mappings`

Mapeia uma partida local para eventos externos de provedores diferentes, como API-Football e TheSportsDB. Isso e necessario porque os provedores nao compartilham os mesmos IDs.

### `broadcast_sources`

Guarda o historico de consultas e evidencias de transmissao. Pode representar uma resposta da TheSportsDB, uma pesquisa da OpenAI ou uma correcao manual. A tabela preserva canais, URLs, resumo da evidencia, resposta bruta sanitizada, modelo utilizado, tokens/chamadas de busca quando disponiveis e confiabilidade calculada pela aplicacao.

### `fixture_broadcasts`

Relaciona uma partida a uma emissora em um pais. A restricao unica `football_fixture_id + broadcaster_id + country_code` impede duplicidade durante sincronizacoes. Como a transmissao possui fonte, confianca, revisao e origem, ela e modelada como entidade propria em vez de pivot anonima.

Essa tabela representa a transmissao selecionada/publicavel. O historico completo permanece em `broadcast_sources`.

Cada transmissao possui `review_status` e `publication_status` independentes dos estados da partida. Isso permite aprovar, publicar ou retirar uma emissora especifica sem retirar o jogo inteiro da agenda.

### `publication_settings`

Guarda o modo persistente de publicacao. O padrao e `manual`; `automatic` sera habilitado futuramente pelo painel quando o processo estiver validado.

### `round_publications`

Controla revisao e publicacao por competicao, temporada e rodada. Sera usado pelo Filament para aprovar/publicar rodada inteira.

## Regras de Banco

- `competitions.provider + competitions.external_id` e unico.
- `teams.provider + teams.external_id` e unico.
- `football_fixtures.provider + football_fixtures.external_id` e unico.
- `fixture_provider_mappings.provider + external_event_id` e unico.
- `broadcast_sources.provider + query_hash` e unico para evitar repeticoes pagas desnecessarias.
- `fixture_broadcasts.football_fixture_id + broadcaster_id + country_code` e unico.
- Foreign keys usam cascade delete para manter consistencia em dados de desenvolvimento/teste.

## Integracoes

- API-Football: jogos, participantes, datas, horarios, rodadas e resultados.
- TheSportsDB: descoberta inicial de canais/plataformas de transmissao.
- OpenAI: fallback controlado para transmissao, com structured output e fontes.
- GitHub: repositorio `maiconrsantana/onde-assistir`.

### API-Football

O contrato interno e `App\Contracts\FootballDataProvider`. A implementacao atual e `App\Integrations\ApiFootball\ApiFootballProvider`.

Endpoints e regras usados:

- Base URL padrao: `https://v3.football.api-sports.io`.
- Autenticacao: header `x-apisports-key`.
- Endpoint: `GET /fixtures`.
- Filtros usados: `league`, `season`, `from`, `to`, `timezone=UTC` e `page`.
- O provider retorna DTOs normalizados e nao grava no banco.
- O comando `football:probe-provider` permite validar a integracao com chave real sem persistir dados.
- O comando `football:sync` usa os DTOs do provider e persiste competicoes, times e partidas de forma idempotente.
- A persistencia fica em `App\Services\Football\FixtureSynchronizer`.

Fontes consultadas:

- https://www.api-football.com/news/post/how-to-get-started-with-api-football-the-complete-beginners-guide
- https://www.api-football.com/news/post/how-to-optimize-api-sports-calls-and-quota-usage
- https://www.api-football.com/news/post/fifa-world-cup-2026-guide-to-using-data-with-api-sports

### Sincronizacao de jogos

`FixtureSynchronizer` usa `updateOrCreate` com as chaves compostas do dominio:

- competicao: `provider + external_id`;
- time: `provider + external_id`;
- partida: `provider + external_id`.

Cada partida e processada em uma transacao propria. Se uma partida estiver invalida, ela registra erro e nao impede a persistencia das demais. O sincronizador nao resolve transmissoes; ele apenas mantem dados esportivos locais atualizados.

### TheSportsDB

O contrato interno para transmissao e `App\Contracts\BroadcastFinder`. A implementacao atual e `App\Integrations\TheSportsDb\TheSportsDbBroadcastFinder`.

O comando `football:resolve-broadcasts` seleciona partidas locais por data e status de resolucao, consulta a TheSportsDB e passa o resultado para `App\Services\Broadcast\BroadcastResolver`.

O resolvedor persiste:

- `broadcast_sources`: historico da consulta, evidencias, payload sanitizado, confianca e resultado;
- `fixture_provider_mappings`: vinculo entre partida local e evento externo da TheSportsDB;
- `broadcasters`: catalogo local de canais/plataformas;
- `fixture_broadcasts`: transmissao selecionada para a partida.

Mesmo quando encontra transmissao, o modo atual permanece manual: a partida fica `resolution_status=resolved`, `review_status=pending` e `publication_status=draft`.

### OpenAI

O fallback de transmissao usa `App\Integrations\OpenAI\OpenAIBroadcastFinder`, tambem atras do contrato `App\Contracts\BroadcastFinder`.

O comando `football:resolve-openai-broadcasts` processa somente partidas futuras com `resolution_status` `not_found`, `uncertain`, `conflicting` ou `error`. Ele aplica TTL para nao repetir pesquisa paga recentemente, salvo quando executado com `--force`.

A chamada usa Responses API com ferramenta de busca web e retorno estruturado por JSON Schema. A aplicacao ainda valida deterministicamente o resultado: `found` so e aceito quando existem canais e ao menos uma URL de evidencia rastreavel. Caso contrario, o resultado vira `uncertain`.

As fontes, citacoes, resumo, tokens e quantidade de chamadas de busca sao preservados em `broadcast_sources`. Transmissoes vindas da OpenAI tambem permanecem em rascunho e aguardando revisao manual.

### Automacao, cache e diagnostico

O Scheduler fica em `routes/console.php`:

- `football:sync --days=14` diariamente as 06:00 em `America/Sao_Paulo`;
- `football:refresh-broadcasts --days=7` diariamente as 16:00 em `America/Sao_Paulo`.

Os eventos usam `withoutOverlapping(120)` para evitar execucoes concorrentes. `onOneServer` nao foi adotado porque o projeto ainda nao tem cache compartilhado multi-instancia configurado.

`football:refresh-broadcasts` roda TheSportsDB e depois OpenAI fallback. Os comandos registram ultimo sucesso/falha em `App\Services\Operations\FootballAutomationStatus`, usando cache. `football:automation-status` exibe esse diagnostico.

As execucoes tambem registram eventos estruturados de inicio, conclusao e erro no log Laravel, com intervalo processado, duracao em milissegundos e totais. O dashboard do Filament exibe a saude das automacoes; uma rotina e considerada atualizada quando concluiu com sucesso nas ultimas 26 horas.

`App\Services\Operations\PublicScheduleCache` centraliza a chave do cache publico futuro. O cache e invalidado apos sincronizacoes/resolucoes bem-sucedidas; falhas preservam o cache anterior.

### Painel administrativo

O painel usa Filament 5 em `/admin`. O acesso exige usuario autenticado com `users.is_admin=true`; o projeto nao cria credenciais padrao.

Recursos atuais:

- partidas: revisao de status, filtros por competicao/time/status e acao **Aprovar e publicar**;
- emissoras: CRUD de nome, slug e tipo;
- transmissoes: criacao/correcao manual, filtros por origem/revisao/fonte e acao **Aprovar transmissao**.

Alteracoes manuais usam `source_type=manual`, geram historico em `broadcast_sources` com `provider=manual` e preservam o usuario responsavel no payload sanitizado. O resolvedor automatico nao sobrescreve transmissao manual existente para a mesma partida, emissora e pais.

Ao aprovar e publicar uma partida, o painel define `review_status=approved`, `publication_status=published`, registra usuario/data de aprovacao, define `published_at`, limpa `needs_review` das transmissoes da partida e invalida o cache publico. Aprovar uma transmissao individual limpa apenas `needs_review`, atualiza `verified_at` e tambem invalida o cache publico.

### Interface publica

A pagina publica fica na rota `/` e usa `App\Http\Controllers\PublicScheduleController`.

A consulta da agenda fica em `App\Services\PublicSchedule\PublishedFixtureSchedule` para manter a regra publica fora da view. A pagina lista somente partidas futuras dos proximos 30 dias com `publication_status=published` e `review_status=approved`.

Transmissoes exibidas ao visitante sao carregadas de `fixture_broadcasts` apenas quando `country_code=BR`, `needs_review=false`, `review_status=approved` e `publication_status=published`. O painel permite editar esses estados e retirar uma transmissao individual do ar. Se uma partida publicada nao tiver transmissao publicavel, a tela mostra "Transmissao ainda nao divulgada".

A request publica nao chama API-Football, TheSportsDB nem OpenAI. Ela consulta dados locais e usa `App\Services\Operations\PublicScheduleCache`, invalidado pelos fluxos de sincronizacao/resolucao quando concluem com sucesso.

### API para clientes mobile

A API publica versionada fica em `/api/v1/fixtures` e `/api/v1/fixtures/{id}`. Ela reutiliza `PublishedFixtureSchedule`, aplica os mesmos estados de aprovacao/publicacao da pagina Blade, oferece filtros por data, competicao e time, e limita o tamanho da pagina. `FixtureResource` controla o contrato JSON, retorna datas em ISO 8601 UTC com o timezone de apresentacao e omite `raw_payload`.

Android e iOS poderao consumir essa API por um cliente nativo ou hibrido. A camada mobile nao deve duplicar regras de publicacao, confiabilidade ou integracao com provedores.

URLs de fontes externas sao normalizadas por `App\Support\ExternalUrl` antes de serem persistidas ou retornadas pela API. Apenas esquemas `http` e `https` com host valido sao aceitos.

## Riscos

- Credenciais de API-Football, TheSportsDB e OpenAI dependem do `.env` local.
- MySQL depende de banco/usuario reais no `.env` local.
- Dados de transmissao podem estar ausentes, atrasados ou conflitantes.
- Regras de timezone precisam continuar cobertas por testes nas proximas telas e jobs.

## Criterios Gerais de Aceite

- Testes automatizados nao podem depender de internet nem consumir creditos.
- Sincronizacoes devem ser idempotentes.
- Falhas de APIs externas devem preservar os ultimos dados validos.
- Informacoes de transmissao sem evidencia nao devem ser publicadas como confirmadas.
- Nenhuma rodada deve ser exibida publicamente enquanto estiver em rascunho.
