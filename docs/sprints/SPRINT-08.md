# Sprint 08 — Desenvolvimento da Obra

## Objetivo
Implementar a quarta etapa funcional do workflow da obra: a central de produção e acompanhamento dos capítulos gerados no Planejamento.

## Fluxo
Identificação → Projeto da Obra → Planejamento → **Desenvolvimento** → Revisão Geral.

Cada capítulo possui seu próprio fluxo interno:

**Preparação → Pesquisa → Redação → Revisão**

## Interface da obra
- cabeçalho e workflow geral preservados;
- indicadores Total, Concluídos, Preparação, Pesquisa, Redação, Revisão e progresso da Obra;
- pesquisa por título/número;
- filtros por etapa do capítulo;
- ordenação por ordem da obra, última edição, título e progresso;
- cards de capítulo com etapa, progresso, palavras e última edição;
- estado vazio orientando a retornar ao Planejamento quando ainda não houver capítulos.

## Workspace do capítulo
Ao abrir um capítulo, o sistema exibe:
- número e título do capítulo;
- etapa atual e percentual concluído;
- workflow interno Preparação → Pesquisa → Redação → Revisão;
- retorno para Desenvolvimento;
- navegação Capítulo anterior / Próximo capítulo;
- placeholder funcional da etapa interna atual, sem antecipar os campos dos próximos sprints.

## Backend
Endpoints REST:
- `GET /books/{id}/development-stage`
- `POST /books/{id}/development-stage/complete`
- `GET /books/{id}/chapters/{chapter_id}`

Os capítulos continuam armazenados como `verbum_chapter`, privados e vinculados à obra e à conta proprietária.

## Regras
- capítulos gerados no Planejamento iniciam em `preparation`;
- progresso interno é baseado nas quatro etapas do capítulo;
- o Desenvolvimento só pode ser concluído quando todos os capítulos tiverem Revisão concluída;
- ao concluir, a etapa atual da obra passa para Revisão Geral;
- alterações estruturais vindas do Planejamento não apagam conteúdo de capítulos existentes.

## Fora do Sprint
Campos funcionais de Preparação, Pesquisa, Redação e Revisão; editor textual; Base de Conhecimento do capítulo; IA; Revisão Geral.
