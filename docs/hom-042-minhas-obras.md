# HOM-042 — Minhas Obras

A central “Minhas Obras” passa a apresentar o caminho editorial oficial de oito etapas, contagens reais, progresso, subetapa, capítulos, palavras e última edição de cada obra.

## Operações administrativas

- A busca considera título, subtítulo, nome interno, autoria, palavras-chave e projeto.
- Filtros combináveis, ordenação e modos grade/lista não modificam os registros.
- Arquivamento é reversível pela própria tela.
- Exclusão de obra não publicada envia o registro para a lixeira do WordPress.
- Obras com edição publicada não podem ter título, subtítulo ou capa alterados e não aceitam exclusão comum.
- Duplicação cria uma nova obra na Identificação, sem copiar capa, capítulos ou publicação.

## Persistência

Os campos administrativos usam metadados da obra. O histórico administrativo fica em `_verbum_library_history`; capas continuam usando os endpoints e metadados existentes. Não há migração SQL nem exclusão de conteúdo preexistente.
