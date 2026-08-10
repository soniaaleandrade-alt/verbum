# Sprint 07 — Planejamento da Obra

## Objetivo
Implementar a terceira etapa funcional do workflow da obra, seguindo a referência aprovada do Bolt: pergunta central, tese, visão geral, metodologia, índice provisório estruturado, organização editorial, metas e geração de capítulos.

## Fluxo
Identificação → Projeto da Obra → **Planejamento** → Desenvolvimento.

## Interface
- Pergunta Central, Tese Principal e Visão Geral da Obra.
- Metodologia, Forma de Apresentação, Abordagem e Estrutura Geral.
- Estrutura da Obra com itens `Parte`, `Capítulo` e `Subcapítulo`.
- Reordenação por arrastar e por controles de posição.
- Organização Editorial: observações, estratégia de escrita e cronograma inicial.
- Meta da Obra: capítulos, palavras e páginas estimadas.
- Geração dos Capítulos com contadores de partes, capítulos e subcapítulos.
- Checklist automático de 10 itens e progresso da etapa.
- Rodapé padrão com Etapa anterior, Salvar e conclusão.

## Backend
Endpoints REST:
- `GET /books/{id}/planning-stage`
- `PATCH /books/{id}/planning-stage`
- `POST /books/{id}/planning-stage/generate-chapters`
- `POST /books/{id}/planning-stage/complete`

A persistência usa metadados privados da obra. Capítulos gerados são registros privados `verbum_chapter`, vinculados à obra e ao item original do índice. A sincronização não apaga conteúdo de capítulos existentes.

## Regras de conclusão
O checklist contém:
1. Pergunta central
2. Tese principal
3. Visão geral
4. Metodologia
5. Forma de apresentação
6. Abordagem
7. Estrutura geral
8. Índice provisório
9. Meta da obra
10. Capítulos gerados

Após a conclusão, `planning` é registrado como concluído e a etapa atual passa para `development`.

## Preservado
Autenticação, perfil, Dashboard, Minhas Obras, Identificação, Projeto da Obra, propriedade por usuário e demais etapas futuras.

## Fora do Sprint
Preparação, Pesquisa, Redação e Revisão de capítulos; conteúdo funcional de Desenvolvimento; Revisão Geral; IA e publicação.
