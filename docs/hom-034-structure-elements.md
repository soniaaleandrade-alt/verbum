# HOM-034 — Estrutura 3 de 4: Elementos

## Escopo

Implementa somente Elementos. Direção e Arquitetura permanecem intactas; Índice Provisório continua reservado. Nenhum elemento é convertido em capítulo e nenhum texto é apagado.

## Persistência

| Conteúdo | Armazenamento |
| --- | --- |
| Elementos, grupos e ordem | `_verbum_structure_elements_items` |
| Revisão e concorrência | `_verbum_structure_elements_revision` |
| Histórico | `_verbum_structure_elements_history` |
| Conclusão | `elements` em `_verbum_structure_substeps` |

Cada elemento possui ID estável, grupo inicial ou final, tipo, título, estado editorial, ativação, ordem independente, referência opcional à Fundação e decisão de uso da Carta.

## Preservação

Elementos antigos são lidos de `_verbum_planning_structure_items` e permanecem em Conteúdos anteriores preservados. A restauração é individual e confirmada, conserva `legacyId` e não altera o registro anterior. Não há migração SQL nem reclassificação destrutiva.

A Carta aos Leitores usa a referência estável `foundation-letter` para consultar `_verbum_foundation_letter_html`. O texto não é copiado para Elementos; alterações feitas na Fundação aparecem na consulta seguinte.

## Estados e ativação

Os estados `included`, `optional`, `later` e `foundation` não determinam o toggle. Desativar retira o elemento da composição atual, mas mantém registro, configuração e conteúdo preservados.

## Inteligência artificial

A análise usa `VERBUM_OPENAI_API_KEY`, é somente leitura e não cria, reordena, ativa, desativa ou remove elementos.

## Conclusão

Exige Arquitetura concluída e tipo, título, ordem e vínculos válidos para os elementos ativos. Não exige quantidade mínima, conteúdo, análise por IA ou resolução dos itens marcados para decidir depois.

## Verificação

- `node --check frontend/app/src/structure-elements-runtime.js`
- `node --check frontend/app/src/structure-architecture-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`
