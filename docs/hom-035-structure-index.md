# HOM-035 — Estrutura 4 de 4: Índice Provisório

## Escopo

Implementa somente o Índice Provisório e conclui a etapa Estrutura. A tela organiza um plano hierárquico e não cria, edita, move nem exclui capítulos reais.

## Persistência

| Conteúdo | Armazenamento |
| --- | --- |
| Árvore provisória | `_verbum_structure_index_items` |
| Expansão dos nós | `_verbum_structure_index_expanded` |
| Sugestões de revisão | `_verbum_structure_index_review_suggestions` |
| Revisão e concorrência | `_verbum_structure_index_revision` |
| Histórico | `_verbum_structure_index_history` |
| Conclusão | `provisional-index` em `_verbum_structure_substeps` |

Os itens usam IDs próprios e tipos `part`, `chapter`, `subchapter`, `element_initial` e `element_final`. Relações são feitas por `parentId`. Uma referência opcional `realChapterId` aponta para capítulo existente sem modificá-lo.

## Preservação e prévia

O índice anterior permanece em `_verbum_planning_structure_items`. Restaurações são individuais e confirmadas. A prévia calcula:

- existentes: itens provisórios com `realChapterId`;
- novos: capítulos provisórios sem referência real;
- possíveis remoções: capítulos reais sem correspondência no plano atual.

“Possível remoção” é somente um conflito de comparação. O capítulo e seu conteúdo permanecem intactos.

## Inteligência artificial

Geração e análise usam `VERBUM_OPENAI_API_KEY`. A geração retorna somente sugestões selecionáveis; a análise cria apontamentos com estados pendente, aceito ou rejeitado. Nenhuma resposta altera a árvore automaticamente.

## Conclusão

Exige as três subetapas anteriores, ao menos uma Parte, um Capítulo provisório, hierarquia válida e nenhuma sugestão pendente. Ao concluir, a etapa Capítulos é liberada sem sincronização. Alterações posteriores no índice marcam nova comparação como necessária.

## Verificação

- `node --check frontend/app/src/structure-index-runtime.js`
- `node --check frontend/app/src/structure-elements-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`
