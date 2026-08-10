# Hotfix 1.0.4 — Acabamento do Dashboard e Sidebar

## Objetivo
Corrigir os problemas visuais restantes do Dashboard oficial identificados no WordPress real após a versão 1.0.3.

## Incluído
- remoção efetiva do cabeçalho provisório `Área Atual / Início` no Painel;
- sidebar com rolagem interna independente para que todos os grupos e menus permaneçam acessíveis em telas de menor altura;
- manutenção do rodapé da sidebar fora da área rolável;
- refinamento do banner editorial, proporções, espaçamentos, grids e cards do Dashboard;
- substituição visual dos glifos que apareciam como quadrados por ícones desenhados em CSS;
- compactação do Dashboard para aproximar a densidade visual da referência aprovada;
- stylesheet de acabamento carregado diretamente pelo WordPress com cache busting por `filemtime`;
- versão do plugin atualizada para `1.0.4`.

## Preservado
- dados já cadastrados;
- Banco de Obras;
- Workspace e workflow;
- Identificação;
- Projeto da Obra;
- Dashboard oficial e dados reais implementados no Sprint Técnico 02;
- autenticação WordPress e tela inteira.

## Critérios de validação
1. Todos os grupos da sidebar podem ser alcançados por rolagem quando a altura da tela é insuficiente.
2. O Painel não exibe o cabeçalho `Área Atual / Início`.
3. Nenhum ícone principal do Dashboard aparece como quadrado sem suporte de fonte.
4. O layout mantém três colunas em desktop e responsividade nas larguras já previstas.
5. A CI permanece verde antes do merge.
