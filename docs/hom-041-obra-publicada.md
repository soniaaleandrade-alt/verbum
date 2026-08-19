# HOM-041 — Obra Publicada

## Objetivo

Disponibilizar a área permanente da obra após a confirmação de uma edição publicada, preservando o registro editorial e separando-o das operações posteriores.

## Implementação

- redirecionamento da confirmação da Publicação para a edição preservada;
- acesso por identificadores de obra e edição, inclusive para a edição mais recente;
- seção `Publicadas` em Minhas Obras;
- abas funcionais de Visão Geral, Dados da Edição, Canais, Arquivos e Histórico;
- dados editoriais da edição em modo somente leitura;
- atualização operacional de canais sem alteração do conteúdo publicado;
- registro de reimpressão, correção administrativa, acesso ao pacote mestre e histórico;
- início controlado de nova versão ou nova edição;
- duplicação como nova obra sem copiar publicação, canais ou histórico;
- mensagem de sucesso limitada à sessão após a confirmação;
- layout responsivo para desktop, tablet e celular.

## Persistência

A edição confirmada continua armazenada em `_verbum_published_editions`. Operações posteriores usam `_verbum_published_operations` e `_verbum_publication_history`, mantendo a edição publicada imutável.

## Rotas

- `GET /verbum/v1/books/{book_id}/published-editions/{edition_id}`
- `POST /verbum/v1/books/{book_id}/published-editions/{edition_id}`

O identificador `latest` resolve a edição publicada mais recente da obra.

## Verificação

- checagem sintática dos runtimes JavaScript;
- homologação do frontend;
- geração do bundle estático;
- verificação de espaços e conflitos no diff;
- conferência das rotas REST no conjunto de testes PHP.
