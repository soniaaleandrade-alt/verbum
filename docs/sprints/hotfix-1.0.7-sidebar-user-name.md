# Hotfix 1.0.7 — Nome da Pessoa na Sidebar

## Objetivo

Substituir o e-mail exibido no rodapé da sidebar pelo nome da pessoa vinculada à conta WordPress.

## Comportamento

- O endpoint `/me` passa a priorizar `first_name + last_name` da conta WordPress.
- Se nome e sobrenome não estiverem preenchidos, o sistema usa o nome de exibição do WordPress como fallback.
- A sidebar mostra o nome da pessoa como informação principal.
- A linha secundária passa a exibir `Minha conta`.
- O e-mail deixa de ser exibido visualmente na sidebar.
- A mesma identificação nominal continua disponível ao restante da interface, inclusive na saudação do Dashboard.

## Preservado

- avatar com inicial;
- logout seguro;
- botão Recolher;
- rolagem interna da sidebar;
- Dashboard oficial;
- Banco de Obras, Workspace, Identificação e Projeto da Obra.

## Versão

`1.0.7`
