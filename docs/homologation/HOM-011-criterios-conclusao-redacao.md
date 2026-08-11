# HOM-011 — Critérios de conclusão da Redação

**Versão alvo:** 2.5.5
**Etapa:** Desenvolvimento > Redação do Capítulo
**Classificação:** Hotfix funcional / UX

## Encontrado
O checklist da Redação apresentava itens de qualidade editorial como se todos fossem obrigatórios. Isso criava a impressão de que a Redação só poderia ser concluída com 100% do checklist, mesmo quando itens como tese contemplada, fontes utilizadas, citações verificadas ou meta de conteúdo não se aplicavam ao capítulo.

Também havia pouca clareza sobre a diferença entre salvar a Redação e concluir a etapa.

## Correção
Os itens passam a ser apresentados em quatro grupos:

- **Obrigatórios:** Introdução desenvolvida; Estrutura principal desenvolvida; Conclusão desenvolvida; Texto revisado pelo autor; Texto pronto para revisão.
- **Recomendados:** Tese contemplada; Meta de conteúdo analisada.
- **Condicionais:** Fontes selecionadas utilizadas; Citações verificadas.
- **Automático:** Redação concluída.

Os itens recomendados e condicionais não bloqueiam a conclusão da Redação quando não forem aplicáveis.

O painel passa a informar exatamente quais requisitos obrigatórios ainda faltam e esclarece que o percentual de progresso também considera verificações editoriais recomendadas, portanto não é necessário chegar a 100% para concluir a Redação.

O botão **Salvar** do rodapé fica disponível durante a Redação e salva sem concluir a etapa. O botão **Salvar e continuar** só é liberado quando os requisitos obrigatórios estiverem atendidos e encaminha para a conclusão da Redação.

## Critérios de reteste
1. Abrir um capítulo na Redação com Introdução, Desenvolvimento e Conclusão preenchidos.
2. Manter Tese contemplada, Fontes selecionadas utilizadas, Citações verificadas e Meta de conteúdo analisada desmarcados.
3. Marcar Texto revisado pelo autor e Texto pronto para revisão.
4. Confirmar que Concluir Redação é liberado mesmo sem 100% do checklist.
5. Confirmar que o painel informa que itens recomendados e condicionais não bloqueiam a etapa.
6. Confirmar que Salvar funciona independentemente da conclusão.
7. Concluir a Redação e confirmar a abertura da Revisão do Capítulo.
