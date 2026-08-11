# Sprint 17 — Diagramação da Obra

**Versão:** 2.3.0
**Etapa:** 9 de 11
**Fluxo:** Mesa Editorial → Diagramação → Trâmites Legais

## Objetivo

Transformar a versão aprovada pela Mesa Editorial em um projeto paginado, preservando a baseline editorial e separando configuração visual de alteração de conteúdo.

## Baseline

A etapa usa `_verbum_editorial_approved_version_id` e `_verbum_editorial_approved_hash`. O snapshot aprovado permanece imutável durante a Diagramação.

## Incluído

- formato 14 × 21 cm, 16 × 23 cm, A5 ou personalizado;
- largura, altura, margens internas/externas, margens espelhadas e sangria;
- presets Clássico, Contemporâneo, Minimalista, Acadêmico, Devocional e Literário;
- tipografia do corpo e títulos, corpo, entrelinha, alinhamento, recuo e espaçamentos;
- hifenização e controle editorial de viúvas/órfãs;
- página-mestre, cabeçalhos, rodapé e posição da paginação;
- numeração romana no pré-textual;
- abertura de capítulos, página nova/ímpar e capitular;
- estilos para citações e categorias religiosas;
- notas de rodapé ou finais;
- configuração do sumário;
- leitura dos elementos, ordem, briefing de capa e briefing de diagramação aprovados na Mesa Editorial;
- prévia paginada aproximada, página simples/espelhada e mapa de páginas;
- estimativa de páginas baseada na configuração tipográfica;
- central de pendências de Diagramação;
- histórico de provas;
- geração de PDF técnico de prova com marca `PROVA — NÃO PUBLICAR`;
- Assistente de Diagramação contextual;
- checklist e aprovação final;
- conclusão da etapa liberando Trâmites Legais.

## PDF de prova

O PDF gerado é uma prova técnica para conferência e histórico. Ele não é declarado PDF/X, arquivo certificado de gráfica, imposição gráfica ou prova de cor profissional.

## Regra editorial

A Diagramação não edita o corpo textual da baseline. Mudanças de conteúdo exigem nova versão, Auditoria e Mesa Editorial antes de uma nova baseline de Diagramação.

## Persistência

- `_verbum_layout_rounds`
- `_verbum_layout_approved_version_id`
- `_verbum_layout_approved_hash`
- `_verbum_layout_final_page_count`
- `_verbum_layout_final_proof_id`
- `_verbum_layout_completed_at`

As rodadas preservam configuração, checklist, pendências e provas por versão editorial.

## Endpoints

- `GET/PATCH /books/{id}/layout-stage`
- `GET /books/{id}/layout-stage/preview`
- `POST /books/{id}/layout-stage/issues`
- `PATCH/DELETE /books/{id}/layout-stage/issues/{issue_id}`
- `POST /books/{id}/layout-stage/proofs`
- `POST /books/{id}/layout-stage/assist`
- `POST /books/{id}/layout-stage/complete`

## Regra para conclusão

Baseline válida, checklist manual concluído, nenhuma pendência aberta, pelo menos uma prova gerada e confirmação explícita da prévia/paginação.

## Fora do Sprint

ISBN, ficha catalográfica oficial, integração com gráfica, imposição, gerenciamento profissional CMYK, certificação PDF/X, editor gráfico avançado de capa e publicação em marketplaces.
