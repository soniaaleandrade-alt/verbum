# Sprint 18 — Trâmites Legais da Obra

**Versão:** 2.4.0
**Etapa:** 10 de 11
**Fluxo:** Diagramação → Trâmites Legais → Publicação

## Objetivo

Organizar os registros, documentos, autorizações, créditos e arquivos finais da edição antes da Publicação, preservando a baseline aprovada na Diagramação.

## Baseline

A etapa usa `_verbum_layout_approved_version_id` e `_verbum_layout_approved_hash`, além dos dados finais de páginas e prova da Diagramação. A baseline de conteúdo permanece imutável.

## Incluído

- painel da edição com versão, formato, páginas, edição e ano;
- checklist legal-editorial e progresso;
- acompanhamento de ISBN por formato, com validação estrutural local sem confirmação junto ao órgão emissor;
- acompanhamento de ficha catalográfica e registro de inserção na edição;
- direitos autorais, copyright e registro autoral opcional;
- conteúdos de terceiros, licenças e autorizações;
- bloqueio da conclusão enquanto houver autorização necessária não resolvida;
- créditos editoriais e dados do expediente;
- Central de Documentos com categoria, status, número, datas, observações e URL/referência de arquivo;
- pendências com prioridade, inclusive bloqueante;
- alertas baseados somente nas datas registradas pelo usuário;
- arquivos finais de impresso e digital;
- registro de Prova Legal Final;
- dados técnicos de produção e gráfica;
- perfis opcionais eclesial e acadêmico;
- histórico de alterações da rodada;
- Assistente Legal-Editorial com restrição explícita contra aconselhamento jurídico e invenção de requisitos;
- congelamento do estado legal ao concluir;
- liberação da etapa Publicação.

## Segurança editorial

Documentos e registros pertencem ao escopo Conta → Obra → Rodada Legal. Os endpoints reutilizam a verificação de propriedade da obra. A rodada concluída torna-se imutável.

O sistema não solicita automaticamente ISBN, ficha catalográfica, registro autoral, autorização eclesial ou qualquer documento externo. URLs informadas pelo usuário são apenas referências armazenadas no contexto privado da obra; a proteção efetiva do arquivo deve ser garantida pelo armazenamento utilizado.

## Validação de ISBN

A validação implementada verifica a estrutura e o dígito verificador de ISBN-10/ISBN-13. Ela não confirma emissão, titularidade ou situação oficial do identificador junto a uma agência.

## Persistência

- `_verbum_legal_rounds`
- `_verbum_legal_approved_version_id`
- `_verbum_legal_approved_hash`
- `_verbum_legal_snapshot_hash`
- `_verbum_legal_final_file`
- `_verbum_legal_completed_at`

A rodada guarda estado, documentos, conteúdos de terceiros, pendências, provas, checklist, histórico e snapshot final.

## Endpoints

- `GET/PATCH /books/{id}/legal-stage`
- `POST /books/{id}/legal-stage/documents`
- `PATCH/DELETE /books/{id}/legal-stage/documents/{document_id}`
- `POST /books/{id}/legal-stage/third-party`
- `PATCH/DELETE /books/{id}/legal-stage/third-party/{item_id}`
- `POST /books/{id}/legal-stage/issues`
- `PATCH/DELETE /books/{id}/legal-stage/issues/{issue_id}`
- `POST /books/{id}/legal-stage/proofs`
- `POST /books/{id}/legal-stage/assist`
- `POST /books/{id}/legal-stage/complete`

## Regra para conclusão

A conclusão exige baseline válida, checklist manual concluído, ISBN e ficha tratados como validados ou não aplicáveis, nenhuma autorização necessária pendente, nenhuma pendência bloqueante aberta, arquivo final selecionado e confirmação explícita do autor.

## Resultado

Ao concluir, o sistema congela o estado legal da edição, registra hash do snapshot, marca `legal` como concluído e move a obra para `publication`.

## Fora do Sprint

Solicitação automática de ISBN, emissão oficial de ficha catalográfica, registro autoral automático, aconselhamento jurídico, assinatura eletrônica de contratos, pagamentos, integração com gráfica, registros externos e publicação em marketplaces.
