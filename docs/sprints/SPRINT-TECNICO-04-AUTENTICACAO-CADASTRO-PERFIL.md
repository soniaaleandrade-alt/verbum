# Sprint Técnico 04 — Autenticação, Cadastro e Perfil

## Objetivo
Criar a camada de identidade do Verbum Studio para que cada pessoa acesse o sistema com conta própria, possa cadastrar e completar seu perfil e tenha seus dados vinculados à identidade autenticada do WordPress sem expor o painel administrativo.

## Escopo
- tela própria de login;
- criação de conta com nome, sobrenome, e-mail, senha e aceite dos termos;
- recuperação e redefinição de senha dentro do Verbum Studio;
- perfil com nome, sobrenome, nome de exibição, telefone, país, idioma, fuso horário e biografia;
- upload de foto de perfil;
- nome e foto refletidos no Dashboard e na sidebar;
- logout dentro da interface;
- papel `verbum_writer` com acesso ao aplicativo, sem acesso normal ao wp-admin;
- sessão expirada retorna para a tela de login;
- estrutura de verificação de e-mail com reenvio e confirmação, sem bloquear o uso nesta primeira versão;
- isolamento de Projetos e Obras continua baseado no usuário proprietário já existente.

## Segurança
- autenticação usa os usuários nativos do WordPress;
- nenhuma senha é armazenada pelo plugin;
- cookies de autenticação são emitidos pelo WordPress;
- REST nonce é renovado após login/cadastro;
- upload limitado a imagem e tamanho máximo de 5 MB;
- usuários comuns do Verbum Studio não devem navegar no `/wp-admin`.

## Fluxo
1. Visitante abre o aplicativo.
2. Verbum exibe Login / Criar conta.
3. Após autenticação, a página é recarregada com sessão e nonce válidos.
4. A pessoa entra no Dashboard.
5. Ao clicar em Minha conta, abre o perfil para completar dados e foto.
6. Sair encerra a sessão e retorna ao Login.

## Fora do escopo
- cobrança e assinatura;
- login social;
- equipes e colaboração;
- SSO;
- autenticação multifator.

Versão prevista: `1.2.0`.
