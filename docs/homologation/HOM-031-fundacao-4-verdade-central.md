# HOM-031 — Fundação 4 de 4: Verdade Central

## Escopo

Implementa somente a quarta subetapa da Fundação da Obra, preservando Carta e Alma, Intenção e Leitor e Resultado. A página Estrutura não foi reformulada.

## Persistência e compatibilidade

Não há migração SQL ou exclusão de dados.

| Conteúdo | Armazenamento |
| --- | --- |
| Tese com formatação | `_verbum_foundation_thesis_html` |
| Tese em texto para compatibilidade | `_verbum_planning_main_thesis` |
| Frase-síntese | `_verbum_foundation_synthesis_phrase` |
| Revisão e atualização | `_verbum_foundation_truth_central_revision` e `_verbum_foundation_truth_central_updated_at` |
| Conclusão | `_verbum_foundation_truth_central_completed_at` |
| Histórico | `_verbum_foundation_truth_central_history` |

Mensagem Central, Pergunta Central, Frase Norteadora e Visão geral permanecem em seus campos de origem e aparecem somente como conteúdos anteriores para revisão. Não há conversão automática.

## Conclusão

A conclusão valida os campos essenciais das quatro subetapas, registra `truth-central` em `_verbum_foundation_substeps`, registra `project` em `_verbum_completed_stages`, preserva datas já existentes e muda `_verbum_stage` para `planning`. Isso libera a Estrutura sem alterar seu conteúdo.

## Inteligência artificial

Os recursos de geração de tese, opções de frase e conferência completa reutilizam `VERBUM_OPENAI_API_KEY`. As sugestões são exibidas antes da inserção e exigem confirmação. A ausência da chave não impede salvamento ou conclusão.

## Verificação

- `node --check frontend/app/src/foundation-truth-central-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`

A validação visual autenticada e a captura em 1536 × 1024 devem ser concluídas no ambiente WordPress de homologação, não disponível neste contêiner.
