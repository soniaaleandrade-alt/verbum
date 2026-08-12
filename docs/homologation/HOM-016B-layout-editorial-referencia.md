# HOM-016B — Layout Editorial fiel à referência

## Objetivo
Aproximar Redação e Revisão do layout de referência aprovado na homologação, com ambiente limpo, branco, editorial e focado no manuscrito.

## Decisões aprovadas
- O Editor Editorial ocupa toda a largura útil e oculta a sidebar principal durante Redação/Revisão.
- Cabeçalho branco com marca Verbum Studio à esquerda, contexto Livro > Capítulo > etapa ao centro e métricas/ações à direita.
- Cor de destaque azul-violeta, fundo branco e divisórias suaves.
- Estrutura do capítulo na esquerda, manuscrito no centro e apoio contextual na direita.
- Barra de ferramentas horizontal e compacta, alinhada como no modelo de referência.
- Scrollbars finas e discretas.
- Painel direito com cinco abas sem overflow horizontal em desktop.
- Manuscrito apresentado como página contínua de livro: sem cards, sem caixas entre seções e com tipografia editorial.
- Títulos de seção e corpo do texto mantêm edição direta, preservando a persistência existente.
- Modo Foco mantém apenas toolbar, página do livro e barra inferior.

## Segurança funcional
Este ajuste não altera as APIs nem o modelo de persistência de Redação/Revisão. Introdução, tópicos e conclusão continuam sendo salvos nos campos já homologados; a mudança é de apresentação e experiência de edição.

## Versão
2.6.2

## Reteste
1. Abrir Redação e conferir o layout branco em tela cheia.
2. Conferir marca, breadcrumb, métricas e ação de conclusão no topo.
3. Conferir toolbar horizontal, sem quebra vertical.
4. Conferir scrollbars finas na estrutura e painel direito.
5. Digitar diretamente no manuscrito, alternando Introdução, Desenvolvimento e Conclusão.
6. Salvar, pressionar F5 e confirmar persistência do texto e estabilidade do layout.
7. Abrir Revisão e confirmar a mesma identidade visual.
8. Conferir modalidades, pendências, fontes, notas e checklist sem regressões.
9. Testar Modo Foco e zoom.
