# Sprint 11 — Redação do Capítulo

**Versão:** 1.7.0
**Etapa interna:** 3 de 4 — Preparação → Pesquisa → Redação → Revisão

## Objetivo

Transformar Preparação e Pesquisa em um ambiente de escrita assistida, seguro e estruturado, no qual o autor nunca começa em uma página em branco.

## Entregas

- Workspace de Redação em três áreas: capítulos, editor central e painel de Pesquisa.
- Editor estruturado em Introdução, Desenvolvimento e Conclusão.
- Estrutura Inicial da Preparação convertida em tópicos editáveis da Redação sem alterar a Preparação original.
- Barra de formatação com negrito, itálico, sublinhado, títulos, listas, citação, imagem por URL, tabela, notas, comentários, desfazer e refazer.
- Fontes selecionadas na Pesquisa disponíveis ao lado do editor e inseríveis no texto.
- Destaque das fontes vinculadas ao tópico ativo.
- Ideias da Pesquisa reutilizáveis como anotações de produção.
- Autosave com indicador Salvando/Salvo e ação Salvar agora.
- Contadores de palavras e caracteres, tempo de escrita, meta própria do capítulo e progresso da meta.
- Histórico de até 30 versões de segurança com snapshots periódicos, manuais e de conclusão.
- Painéis laterais recolhíveis e Modo Foco.
- Assistente de Escrita contextual, acionado pelo servidor, com proposta que precisa ser aceita ou descartada pelo autor e regra explícita de não inventar fontes.
- Checklist da Redação e conclusão da etapa, liberando Revisão e levando o capítulo a 75%.
- Persistência isolada por conta, obra e capítulo.

## Regra para liberar Revisão

A Redação exige conteúdo em Introdução, em todos os tópicos de Desenvolvimento e em Conclusão, além da confirmação “Texto pronto para revisão”. A meta de palavras não bloqueia a conclusão.

## Assistente de Escrita

O endpoint permanece exclusivamente no servidor. A chave nunca é enviada ao navegador. Quando `VERBUM_OPENAI_API_KEY` não estiver configurada, o restante da Redação continua funcionando normalmente e o Assistente informa que a configuração segura é necessária.

O Assistente recebe apenas o contexto do capítulo e as fontes registradas na Pesquisa. A proposta nunca substitui o texto automaticamente: o autor escolhe Aceitar ou Descartar e pode editar a sugestão antes de aceitar.

## Segurança editorial

Voltar para Preparação ou Pesquisa, trocar de capítulo, atualizar a página ou avançar para Revisão não apaga a Redação. Fontes e ideias usadas permanecem rastreáveis, e o conteúdo consolidado atualiza a contagem de palavras do capítulo e da obra.

## Fora do escopo

Revisão ortográfica completa, revisão doutrinal, revisão editorial, comparação avançada de versões, Revisão Geral da Obra, Diagramação e Publicação.
