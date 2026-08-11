# HOM-010 — Ideias e imagens na Redação

**Versão alvo:** 2.5.4
**Etapa:** Desenvolvimento > Redação do Capítulo
**Classificação:** Hotfix funcional / UX

## Encontrado
Na Redação, as fontes selecionadas na Pesquisa podiam ser inseridas diretamente no manuscrito, mas as ideias ofereciam somente a ação `Inserir como anotação`, que na prática criava uma nota lateral. Isso tornava pouco claro como aproveitar uma ideia diretamente no texto.

A ferramenta `Imagem` também solicitava uma URL externa, em vez de permitir o envio de um arquivo do computador.

## Correção
- cada ideia passa a oferecer duas ações distintas:
  - `Inserir na Redação`, para colocar a ideia no ponto ativo do manuscrito;
  - `Adicionar às notas`, preservando o comportamento de apoio lateral já existente;
- a ação `Imagem` deixa de solicitar URL;
- ao clicar em `Imagem`, o sistema abre o seletor de arquivos do computador;
- aceita JPG, PNG, WEBP e GIF, com limite de 10 MB;
- a imagem é enviada de forma autenticada para a Biblioteca de Mídia do WordPress e inserida no ponto ativo da Redação;
- imagens inseridas ficam responsivas dentro da página de escrita;
- o papel `verbum_writer` recebe a capacidade mínima `upload_files` para permitir o envio pela API nativa do WordPress;
- o hotfix é carregado somente na etapa Desenvolvimento para não aumentar o caminho crítico do Painel e de Minhas Obras.

## Critérios de reteste
1. Abrir um capítulo em Redação e clicar dentro do texto.
2. Em `Ideias da Pesquisa`, confirmar as ações `Inserir na Redação` e `Adicionar às notas`.
3. Inserir uma ideia no manuscrito, salvar, pressionar F5 e confirmar persistência.
4. Usar `Adicionar às notas` e confirmar que a ideia aparece em `Notas e comentários`.
5. Clicar em `Imagem` e confirmar que abre o seletor de arquivos do computador, sem pedir URL.
6. Enviar uma imagem JPG/PNG/WEBP/GIF de até 10 MB e confirmar inserção no ponto ativo.
7. Salvar, pressionar F5 e confirmar que a imagem permanece no manuscrito.
8. Confirmar que a contagem de palavras e o restante do editor continuam funcionando normalmente.
