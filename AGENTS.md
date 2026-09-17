# AGENTS.md

## Projeto

Onde Passa o Jogo e uma aplicacao Laravel 12 para listar partidas, horarios e transmissoes do Campeonato Brasileiro no Brasil.

## Comandos

- Instalar PHP: `composer install`
- Preparar ambiente: `cp .env.example .env && php artisan key:generate`
- Ativar Node local: `source .node-env`
- Instalar frontend: `source .node-env && npm install`
- Testes: `php artisan test`
- Formatacao PHP: `./vendor/bin/pint`
- Build frontend: `source .node-env && npm run build`
- Servidor local: `php artisan serve`
- Diagnosticar API-Football sem persistir: `php artisan football:probe-provider --from=YYYY-MM-DD --to=YYYY-MM-DD`
- Sincronizar jogos no banco: `php artisan football:sync --from=YYYY-MM-DD --to=YYYY-MM-DD`

## Regras do projeto

- Nunca versionar `.env`, tokens, senhas, `vendor`, `node_modules`, `.tools` ou builds locais.
- Usar MySQL para desenvolvimento/producao. Testes podem usar MySQL quando houver banco de teste configurado; caso contrario, devem usar fakes e isolamento sem depender de servicos externos.
- Persistir datas de eventos em UTC quando modelarmos partidas; exibir em `America/Sao_Paulo`.
- A pagina publica deve ler dados locais persistidos, sem chamar APIs externas durante a request.
- API-Football sera a fonte primaria para jogos, equipes, datas, horarios, rodadas e resultados.
- TheSportsDB deve ser consultada antes da OpenAI para descobrir transmissoes.
- OpenAI deve ser fallback para transmissoes ausentes/conflitantes e sempre com evidencias rastreaveis.
- Integracoes externas devem ficar atras de interfaces/jobs e usar fakes nos testes.
- Dados de transmissao sem fonte confiavel devem aparecer como "Transmissao ainda nao divulgada".
- O modo padrao de publicacao e manual; a pagina publica so pode exibir registros publicados.
- Atualizar `docs/PROJECT_STATUS.md` ao concluir cada etapa.
