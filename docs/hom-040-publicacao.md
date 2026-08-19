# HOM-040 — Publicação

## Escopo

A etapa 8 de 8 reúne Planejamento, Canais e Distribuição, Lançamento e Edição Publicada.

## Origem e persistência

O fluxo lê a versão editorial, os arquivos finais e os pacotes aprovados em `_verbum_editorial_preparation`. O rascunho é isolado por obra em `_verbum_publication_journey`, sem alterar os arquivos de origem.

As ações do lançamento são sincronizadas de forma idempotente em `_verbum_editorial_calendar_events`, vinculadas à obra e identificadas como originadas da Publicação.

## Confirmação

A confirmação exige as três etapas anteriores, dados bibliográficos, disponibilidade confirmada, arquivos finais, checklist e declaração explícita. O registro imutável é acrescentado a `_verbum_published_editions` com uma chave de confirmação, impedindo duplicidade.

Depois da confirmação, o rascunho fica bloqueado, o progresso passa a 100%, o status da obra passa a `published` e a obra permanece disponível no gerenciamento e na seção Publicadas de Minhas Obras.

Nova versão ou nova edição exige motivo e mantém a relação com a edição anterior.

## Ações externas

O módulo organiza canais, links, contratos, comprovantes, mensagens e materiais. Nenhum cadastro, upload, convite, mensagem ou publicação externa é executado automaticamente. Um canal somente pode constar como disponível após confirmação explícita.

## Rotas

- `GET /books/{id}/publication-stage/journey`
- `POST /books/{id}/publication-stage/journey`
- rotas anteriores de Publicação permanecem disponíveis para compatibilidade.

## Banco de dados

Não há migração SQL nem exclusão de dados. Toda persistência nova utiliza metadados aditivos da obra.
