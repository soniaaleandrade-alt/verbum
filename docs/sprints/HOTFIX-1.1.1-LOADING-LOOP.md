# Hotfix 1.1.1 — Loop de carregamento após Minhas Obras

## Sintoma

Após a implantação do Sprint Técnico 03, o Verbum Studio podia permanecer indefinidamente na tela **Carregando Verbum Studio...**.

## Causa

O runtime de **Minhas Obras** usa `MutationObserver` para manter nomenclaturas compatíveis com o runtime estático. Algumas atualizações de `textContent` eram executadas mesmo quando o texto já estava correto. Essas escritas geravam novas mutações, que acionavam o observer novamente, criando um ciclo de microtarefas no navegador.

O caso do botão de retorno também usava uma correspondência ampla por `Obras`, que continuava correspondendo depois de o texto já ser `Minhas Obras`.

## Correção

- alterações de texto passam a ocorrer somente quando o valor realmente precisa mudar;
- retorno do Workspace passa a converter somente `‹ Obras` para `‹ Minhas Obras`;
- adicionadas verificações de regressão para impedir a volta de escritas não idempotentes dentro do observer;
- versão atualizada para `1.1.1`.

## Preservado

A página Minhas Obras, seus filtros, cards, pesquisa, workflow, Projetos, Obras, Dashboard, Identificação, Projeto da Obra e dados existentes permanecem preservados.
