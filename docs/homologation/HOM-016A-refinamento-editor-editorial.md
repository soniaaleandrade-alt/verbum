# HOM-016A — Refinamento do Editor Editorial

Versão: 2.6.1

## Contexto
Após a primeira homologação visual do HOM-016, a arquitetura geral do Editor Editorial Unificado foi aprovada. O reteste revelou três ajustes de acabamento necessários antes da homologação funcional.

## Ajustes executados

1. Toolbar horizontal
   - mantém Parágrafo, fonte, tamanho e comandos editoriais na mesma linha;
   - impede que os selects ocupem 100% da largura e se empilhem verticalmente;
   - permite rolagem horizontal somente quando a viewport for realmente estreita.

2. Proporção das três colunas
   - esquerda: 235 px em desktop amplo;
   - centro: flexível, com mínimo editorial de 600 px;
   - direita: 340 px;
   - manuscrito com largura máxima ampliada para 860 px.

3. Painel direito
   - abas distribuídas em cinco colunas iguais;
   - remove scrollbar horizontal em desktop;
   - mantém fallback responsivo em viewports menores.

4. Ajustes de espaçamento
   - reduz paddings laterais sem comprometer legibilidade;
   - preserva o manuscrito como área visual dominante.

## Não altera
- APIs;
- persistência da Redação;
- persistência da Revisão;
- fontes, imagens, notas ou pendências;
- regras de conclusão;
- HOM-011, HOM-012, HOM-013, HOM-014 e HOM-016.

## Reteste
1. abrir Redação e confirmar toolbar em uma única linha;
2. abrir Revisão e confirmar toolbar em uma única linha;
3. confirmar ausência de scrollbar horizontal nas abas do painel direito em desktop;
4. conferir proporções das três colunas;
5. validar F5 sem regressão visual;
6. depois retomar a homologação funcional do HOM-016.
