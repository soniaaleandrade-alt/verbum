# HOM-037 — Revisão Geral da Obra

## Escopo

A Revisão Geral passa a possuir cinco subetapas persistidas: Estrutura, Argumento, Doutrina e Fontes, Unidade e Estilo e Fechamento.

## Persistência aditiva

Foram acrescentadas metainformações para subetapas concluídas, data de conclusão de cada subetapa e versões destinadas à Validação. Nenhuma tabela, coluna, obra, parte, capítulo, texto, fonte, comentário, pendência ou versão existente é removida.

## Proteção do manuscrito

- análises produzem apenas sugestões;
- sugestões só se tornam pendências por ação explícita;
- o manuscrito não é reordenado ou reescrito pela Revisão Geral;
- a conclusão cria uma cópia imutável com hash, capítulos, estrutura, elementos editoriais, fontes e pendências;
- o manuscrito editável permanece independente;
- novas conclusões geram novas versões e preservam as anteriores.

## Fluxo

Cada subetapa exige seus critérios essenciais. A conclusão geral exige as cinco subetapas, ausência de pendências críticas, checklist completo e confirmação final. O destino funcional é a rota `versions`, apresentada na jornada como Validação, correspondente à etapa 6 de 8.

## Rotas

- `GET|PATCH /books/{id}/general-review`
- `GET /books/{id}/general-review/reading`
- `POST|PATCH /books/{id}/general-review/issues`
- `POST /books/{id}/general-review/assist`
- `POST /books/{id}/general-review/substeps/{substep}/complete`
- `POST /books/{id}/general-review/complete`
