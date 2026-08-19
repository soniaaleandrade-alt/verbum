# HOM-039 — Preparação Editorial

## Escopo

A etapa 7 de 8 possui cinco fases persistidas: Texto Definitivo, Direitos e Registros, Projeto Gráfico, Provas e Arquivos Finais.

## Origem e proteção

O módulo carrega a versão final criada pela Validação. O identificador e o hash dessa versão permanecem registrados como origem imutável. Alterações editoriais são armazenadas em `_verbum_editorial_preparation`, sem sobrescrever capítulos, pareceres, correções ou versões anteriores.

Projetos gráficos, provas, observações, aprovações por formato, documentos e pacotes possuem identificadores próprios e histórico. Novos arquivos e provas são acrescentados como novas versões; não são identificados somente pelo nome de exibição.

## Limites institucionais

O sistema registra situação, número, instituição, responsável, comprovante e observações. Não emite ISBN, ficha catalográfica, registro autoral, autorização legal, nihil obstat, imprimatur ou aprovação eclesiástica.

## Fluxo

- confirmação do texto definitivo e da ordem editorial;
- organização de direitos, permissões e registros;
- versão persistida do projeto gráfico;
- provas e aprovações independentes por formato;
- pacotes finais, metadados e integridade;
- versão editorial de fechamento;
- avanço para `publication`, apresentado como Publicação, etapa 8 de 8.

## Rotas

- `GET /books/{id}/editorial-desk`
- `POST /books/{id}/editorial-desk/preparation`
- rotas legadas da Mesa Editorial continuam disponíveis para compatibilidade.

## Banco de dados

Não há migração SQL, remoção de tabela, coluna ou registro. A persistência é aditiva em metadados da obra.
