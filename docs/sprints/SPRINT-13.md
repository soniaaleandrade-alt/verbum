# Sprint 13 — Revisão Geral da Obra

**Versão:** 1.9.0
**Etapa da obra:** 5 de 11
**Fluxo:** Identificação ✓ → Projeto ✓ → Planejamento ✓ → Desenvolvimento ✓ → Revisão Geral

## Objetivo

Revisar o livro como uma unidade editorial, verificando coerência, continuidade, repetições, lacunas, estrutura, linguagem, referências e alinhamento com a direção original da obra antes do Controle de Versões.

## Entregas

- resumo real de capítulos, palavras, capítulos concluídos, pendências e progresso;
- visão global dos capítulos com acesso direto à Revisão do capítulo;
- leitura contínua da obra com sumário lateral baseado no Planejamento;
- modos de revisão: Coerência, Continuidade, Repetições, Lacunas, Estrutura, Linguagem, Referências e Editorial;
- comparação da Direção Original com o resultado final da obra;
- avaliações de objetivo, mensagem central, público, transformação, Pergunta Central e Tese Principal;
- análise e registro das transições entre capítulos;
- terminologia global da obra;
- Prefácio, Apresentação, Nota do Autor, Introdução da Obra e Conclusão da Obra;
- pendências gerais tipadas, vinculáveis a capítulos e com prioridades Baixa, Média, Alta e Crítica;
- sinalização de capítulos alterados depois da Revisão individual ou durante a Revisão Geral;
- Assistente de Revisão Geral baseado em resumos estruturados dos capítulos, sem enviar automaticamente o livro inteiro;
- checklist global e confirmação final do autor;
- snapshots completos de pré-conclusão e conclusão;
- conclusão liberando Controle de Versões;
- endpoints REST próprios e isolamento por conta e obra.

## Regra de conclusão

A Revisão Geral só pode ser concluída quando:

1. todos os capítulos estiverem concluídos;
2. objetivo, Pergunta Central, Tese, estrutura, continuidade, repetições, lacunas, linguagem, referências e textos editoriais estiverem conferidos;
3. não houver pendência crítica aberta;
4. o autor confirmar que a versão geral da obra está pronta.

Pendências de prioridade Baixa, Média ou Alta podem permanecer registradas para acompanhamento, mas ficam preservadas no snapshot. Pendências Críticas bloqueiam a conclusão.

## Assistente

O Assistente recebe a direção da obra e, para cada capítulo, título, contagem de palavras, trecho estruturado da introdução, títulos das seções e trecho da conclusão. Ele não recebe automaticamente o livro integral e não pode inventar citações, documentos, autores ou referências.

## Snapshot

Ao concluir são criados snapshots de segurança com estrutura, textos editoriais, ordem dos capítulos, conteúdo dos capítulos, contagem de palavras e data. Esses registros serão utilizados pelo Sprint 14 — Controle de Versões.

## Fora deste Sprint

Comparação avançada entre versões, rollback completo, Auditoria, Mesa Editorial, Diagramação, Trâmites Legais e Publicação.
