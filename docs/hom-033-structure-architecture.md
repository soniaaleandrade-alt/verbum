# HOM-033 — Estrutura 2 de 4: Arquitetura

## Escopo

Implementa somente Arquitetura. Direção permanece intacta; Elementos e Índice Provisório continuam reservados. A tela não cria, apaga, duplica nem move capítulos.

## Persistência

| Conteúdo | Armazenamento |
| --- | --- |
| Partes e ordem | `_verbum_structure_architecture_parts` |
| Parte selecionada | `_verbum_structure_architecture_selected_part_id` |
| Revisão e concorrência | `_verbum_structure_architecture_revision` |
| Histórico | `_verbum_structure_architecture_history` |
| Conclusão | `architecture` em `_verbum_structure_substeps` |

Cada parte usa ID estável e guarda título, função, tema, resultado, transições, movimento por ID, estimativa, origem, estado e ordem. A origem pode ser `imported`, `ai` ou `manual`; editar uma parte importada mantém sua origem e registra estado `changed`.

## Compatibilidade

As partes antigas são lidas de `_verbum_planning_structure_items` e só entram na nova Arquitetura após confirmação. O `legacyId` impede nova importação da mesma parte. Título, descrição, posição e contagem de capítulos são reaproveitados, mas os registros e vínculos antigos não são alterados. Estrutura Geral e observações editoriais continuam disponíveis em Conteúdos preservados.

## Inteligência artificial

Geração e análise usam `VERBUM_OPENAI_API_KEY`. A geração cria apenas uma prévia; adicionar partes exige seleção e confirmação. A análise é somente leitura. Nenhum fluxo de IA altera títulos, ordem, capítulos ou vínculos automaticamente.

## Conclusão

Exige Direção concluída, pelo menos uma parte e, em todas as partes, título, função, tema central, resultado esperado e movimento relacionado existente. Transições, estimativas, aprovação e IA não são obrigatórias.

## Verificação

- `node --check frontend/app/src/structure-architecture-runtime.js`
- `node --check frontend/app/src/structure-direction-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`
