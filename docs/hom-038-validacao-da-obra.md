# HOM-038 — Validação da Obra

## Escopo

A etapa 6 de 8 possui quatro fases persistidas: Preparação, Pareceres, Correções e Aprovação. A Aprovação mantém os estados de conferência dos resultados e confirmação final no mesmo passo.

## Persistência e proteção

O processo usa metadados aditivos em `_verbum_validation_process`. A versão consolidada da Revisão Geral permanece imutável. Pareceres, observações, decisões e correções usam identificadores estáveis; cada correção registra a observação de origem, redação anterior, nova redação, justificativa, pessoa responsável, estado e data.

Ao concluir Pareceres, uma cópia protegida é criada para as correções. Ao encerrar a Validação, outra versão protegida e imutável é criada como versão validada. Nenhuma versão anterior é apagada ou substituída.

## Fluxo

- Revisão Geral concluída e versão consolidada existente;
- preparação de responsáveis, escopos, prazos e materiais;
- recebimento e classificação dos pareceres;
- correções rastreáveis em versão separada;
- decisão eclesiástica apenas registrada, nunca concedida pelo sistema;
- decisão editorial interna e confirmação final;
- criação da versão validada;
- avanço para `editorial_desk`, apresentado como Preparação Editorial, etapa 7 de 8.

## Rotas

- `GET /books/{id}/versions-stage`
- `POST /books/{id}/versions-stage/validation`
- rotas históricas de leitura, comparação e preservação de versões em `/versions-stage/versions`.

## Compatibilidade

Não há migração SQL, remoção de tabela, coluna ou registro. Os dados anteriores do Controle de Versões e da Auditoria permanecem disponíveis para compatibilidade e histórico.
