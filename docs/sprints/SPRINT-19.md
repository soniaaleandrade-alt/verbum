# Sprint 19 — Publicação da Obra

**Versão:** 2.5.0
**Etapa:** 11 de 11
**Fluxo:** Trâmites Legais → Publicação → Obra Publicada

## Objetivo

Registrar e organizar a edição efetivamente publicada, preservando a baseline legal, os arquivos de referência, metadados, canais, datas e histórico de publicação.

## Baseline

A etapa consome o snapshot final imutável dos Trâmites Legais (`_verbum_legal_snapshot_hash`) e valida também o ID/hash da versão editorial vinculada. A publicação não modifica esse snapshot.

## Incluído

- pacote final da edição com arquivo, capa, identificadores e hash legal;
- metadados comerciais separados do conteúdo editorial;
- palavras-chave, categorias, descrição e dados do autor;
- estratégia de preço por formato e estimativa simples de margem;
- canais de publicação cadastráveis pelo usuário;
- canal obrigatório ou opcional e status independente;
- estados Não iniciado, Preparando, Enviado, Em análise, Aprovado, Agendado, Publicado, Requer ajuste, Suspenso, Encerrado e Não aplicável;
- URL, identificador externo, arquivo de referência e datas por canal;
- data prevista e data efetiva de lançamento;
- tarefas de pré-lançamento, lançamento e pós-lançamento;
- materiais de divulgação e Release Editorial;
- Assistente de Publicação contextual;
- verificação de consistência entre metadados, arquivo e baseline legal;
- checklist final e confirmação explícita do autor;
- registros imutáveis por canal publicado;
- hash final da edição publicada;
- histórico de atualizações pós-publicação sem apagar o registro original;
- tela final `Obra Publicada`, 11/11 etapas e 100% do workflow.

## Regra para conclusão

A Publicação só pode ser concluída quando a baseline legal estiver íntegra, pacote final e capa estiverem definidos, metadados e preços mínimos estiverem preenchidos, houver pelo menos um canal obrigatório, todos os canais obrigatórios estiverem `Publicado` ou `Não aplicável`, existir pelo menos um canal efetivamente publicado, a data efetiva de lançamento estiver registrada, o checklist manual estiver completo e o autor confirmar a publicação.

## Registro publicado

Ao concluir, a rodada é congelada e o Verbum Studio registra:

- snapshot da publicação;
- hash da edição;
- canais publicados;
- URL e identificador externo;
- arquivo de referência e hash da referência;
- data de publicação;
- preço/moeda cadastrados;
- status da obra como `published` / `Publicado`;
- etapa `publication` em `_verbum_completed_stages`.

O hash do arquivo por canal é um hash da referência cadastrada (URL/caminho) quando o binário não está disponível ao servidor; não é apresentado como checksum criptográfico do conteúdo remoto.

## Atualizações pós-publicação

Correções e atualizações posteriores são registradas separadamente em `_verbum_publication_updates`. Elas não reescrevem a publicação original. Mudanças de conteúdo devem seguir o fluxo de versionamento e as etapas editoriais necessárias antes de substituir uma edição publicada.

## Endpoints

- `GET/PATCH /books/{id}/publication-stage`
- `POST /books/{id}/publication-stage/channels`
- `PATCH/DELETE /books/{id}/publication-stage/channels/{channel_id}`
- `POST /books/{id}/publication-stage/tasks`
- `PATCH/DELETE /books/{id}/publication-stage/tasks/{task_id}`
- `POST /books/{id}/publication-stage/updates`
- `POST /books/{id}/publication-stage/assist`
- `POST /books/{id}/publication-stage/complete`

## Fora do Sprint

Envio automático a marketplaces, pagamentos, royalties importados das lojas, estoque/pedidos completos, emissão fiscal, publicidade automática, campanhas de anúncios e clonagem automática integral para uma nova edição.

## Próxima fase recomendada

Com o workflow editorial principal concluído, a próxima fase recomendada é uma homologação técnica completa, percorrendo as 11 etapas em WordPress real, com testes de persistência, navegação, responsividade, segurança, mensagens, desempenho e consistência visual.
