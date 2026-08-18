# HOM-032 — Estrutura 1 de 4: Direção

## Escopo

Implementa somente a primeira subetapa da Estrutura da Obra. Arquitetura, Elementos e Índice Provisório permanecem reservados. Nenhuma Parte ou Capítulo é criado ou alterado.

## Persistência

Não há migração SQL nem transformação automática de conteúdo antigo.

| Conteúdo | Armazenamento |
| --- | --- |
| Eixo | `_verbum_structure_direction_axis` |
| Fio condutor | `_verbum_structure_direction_thread` |
| Ordem teológica | `_verbum_structure_direction_theological_order` |
| Ponto de partida | `_verbum_structure_direction_starting_point` |
| Ponto de chegada | `_verbum_structure_direction_arrival_point` |
| Movimento | `_verbum_structure_direction_movement` |
| Histórico | `_verbum_structure_direction_history` |
| Conclusão | `_verbum_structure_direction_completed_at` e `_verbum_structure_substeps` |

As duas listas guardam objetos com `id`, `text` e `order`. Identificadores temporários são substituídos no servidor por IDs estáveis. A antiga Estrutura Geral, Visão geral e Estratégia de escrita permanecem separadas como conteúdo anterior para revisão.

## Fundação

Tese e frase-síntese são lidas diretamente da Fundação 4 e mostradas somente para consulta. Não são copiadas para campos da Direção. A conclusão exige que `project` esteja registrado em `_verbum_completed_stages`.

## Inteligência artificial

A conferência usa `VERBUM_OPENAI_API_KEY`. Sugestões para campos textuais são apresentadas antes da aplicação e exigem confirmação. A análise não é obrigatória para avançar.

## Verificação

- `node --check frontend/app/src/structure-direction-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`

Arquitetura, Elementos e Índice Provisório exibem somente o estado reservado até suas respectivas implementações.
