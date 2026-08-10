# Hotfix 1.2.1 — Integração do Perfil com a Sidebar

## Objetivo
Refinar a experiência da conta do Verbum Studio depois da implementação do Sprint Técnico 04.

## Escopo
- corrigir a apresentação da foto no rodapé da sidebar;
- usar avatar circular com corte proporcional, sem deformação;
- usar o Nome de exibição como identificação principal da pessoa;
- tornar o bloco de perfil da sidebar clicável para abrir Minha conta;
- manter a tela Minha conta aberta após Salvar alterações;
- atualizar nome e foto visíveis na interface sem recarregar ou redirecionar para o Dashboard;
- exibir confirmação discreta de salvamento;
- remover o rótulo “Área atual” dos cabeçalhos do aplicativo;
- preservar autenticação, projetos, obras, workspace e dados existentes.

## Critérios de aceite
1. Foto da sidebar em avatar circular de 42x42 px com `object-fit: cover`.
2. Sem foto, exibir iniciais.
3. Nome mostrado na sidebar corresponde ao Nome de exibição do perfil.
4. Clique no avatar/nome abre Minha conta.
5. Salvar perfil não fecha o modal e não recarrega a página.
6. Nome/foto alterados aparecem imediatamente na sidebar e no cabeçalho quando aplicável.
7. A mensagem “Alterações salvas” aparece dentro da tela de perfil.
8. “Área atual” deixa de aparecer nos cabeçalhos do aplicativo.
9. A sidebar recolhida mostra apenas o avatar sem deformação.
