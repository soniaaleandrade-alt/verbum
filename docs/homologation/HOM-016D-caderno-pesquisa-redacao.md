# HOM-016D — Caderno de escrita + pesquisa integrada

**Versão:** 2.6.3

## Objetivo
Refinar a Redação do Capítulo para se comportar como um caderno/livro, reduzindo elementos estruturais dentro do manuscrito e trazendo o material da Pesquisa para o próprio centro de escrita.

## Implementação
- No manuscrito permanece visível apenas o título do capítulo e o texto corrido.
- Os rótulos Introdução, Desenvolvimento, Conclusão, títulos internos e o botão Adicionar tópico deixam de aparecer no centro de escrita.
- A estrutura continua preservada internamente e permanece disponível na coluna esquerda para navegação.
- O editor deixa de usar rolagem interna no texto; os campos crescem conforme o conteúdo.
- O manuscrito recebe aparência de folhas editoriais com guias discretas de página, mantendo o texto editável e a persistência existente.
- A Pesquisa do capítulo passa a aparecer diretamente acima das folhas, com fontes e ideias disponíveis para consulta durante a escrita.
- Fontes continuam podendo ser citadas e ideias podem ser enviadas às notas, mas não é necessário inserir nada para visualizar o material pesquisado.
- O fluxo Concluir Redação ganha uma correção de fallback: ao concluir, a ação salva o conteúdo, confirma as flags autorais necessárias e chama a conclusão da Redação pela API.
- Se faltar texto inicial, desenvolvimento ou fechamento, uma mensagem visível informa exatamente o que falta.
- Após conclusão bem-sucedida, o editor abre a etapa Revisão do mesmo capítulo.

## Segurança funcional
- Introdução, seções e conclusão continuam sendo persistidas nos campos já existentes.
- A Pesquisa original não é alterada nem duplicada no banco; a bandeja central apenas apresenta os dados existentes.
- Nenhuma API de Revisão, pendências, notas ou fontes foi removida.

## Reteste
1. Abrir a Redação e conferir que somente o título do capítulo aparece como título no manuscrito.
2. Confirmar que Desenvolvimento, Conclusão e Adicionar tópico não aparecem no centro.
3. Digitar texto longo e confirmar ausência de scrollbar interna no manuscrito.
4. Conferir as guias de folhas/páginas à medida que o conteúdo cresce.
5. Conferir fontes e ideias da Pesquisa na bandeja central.
6. Salvar, pressionar F5 e confirmar persistência.
7. Clicar em Concluir Redação e validar mensagem de requisitos quando houver conteúdo faltante.
8. Com o conteúdo completo, concluir e confirmar avanço para Revisão.
