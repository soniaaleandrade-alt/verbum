# Sprint 16 — Mesa Editorial

**Versão:** 2.2.0
**Etapa da obra:** 8 de 11
**Fluxo:** Auditoria ✓ → Mesa Editorial → Diagramação

## Objetivo

Transformar a versão aprovada na Auditoria em uma versão editorialmente definida e apta para produção gráfica, consolidando identidade, posicionamento, sinopse, quarta capa, perfil do autor, elementos editoriais, briefings e parecer final.

## Entregas

- Mesa Editorial vinculada ao ID e hash exatos da versão aprovada na Auditoria;
- rodadas editoriais preservadas por versão;
- Ficha Editorial com título, subtítulo, autor, gênero, categoria, idioma, público e sinopses;
- posicionamento editorial baseado no Projeto da Obra;
- avaliação de adequação ao público, proposta, estrutura, extensão, progressão, título, subtítulo, diferencial, consistência e preparação para publicação;
- textos de quarta capa e apresentação do autor;
- perfil editorial do autor separado do perfil da conta;
- definição dos elementos pré/pós-textuais e ordem editorial final;
- decisão de formato, edição, ano, local, editora/publicação independente e formato físico pretendido;
- Briefing de Diagramação;
- Briefing de Capa;
- Parecer Editorial com pontos fortes, atenção, recomendações, riscos e conclusão;
- perfil opcional para obras religiosas;
- ajustes editoriais classificados entre não estruturais e alterações de conteúdo;
- qualquer ajuste de conteúdo bloqueia a aprovação da mesma rodada e exige nova versão + nova Auditoria;
- Assistente Editorial contextual sem alteração automática dos dados;
- checklist final, confirmação explícita do autor e decisão editorial;
- aprovação registrando ID, hash e data da versão;
- versão aprovada protegida e marcada como aprovada na Mesa Editorial;
- conclusão liberando Diagramação;
- endpoints REST, interface React, runtime de produção, estilos, testes e documentação.

## Estados da rodada

- **Em avaliação** — decisões ainda em andamento;
- **Ajustes solicitados** — existem ajustes editoriais ou necessidade de nova Auditoria;
- **Pronta para decisão** — preparação concluída, aguardando confirmação final;
- **Aprovada para Diagramação** — versão editorial definida e preservada.

## Regra para ajustes de conteúdo

A Mesa Editorial não altera silenciosamente o corpo auditado. Quando um ajuste de conteúdo é registrado, a rodada atual fica impedida de ser aprovada. O autor deve corrigir a obra de trabalho, criar uma nova versão, submetê-la novamente à Auditoria e retornar à Mesa Editorial em nova rodada.

Ajustes como sinopse, quarta capa, perfil do autor, briefing e demais decisões editoriais que não alteram o corpo auditado podem ser resolvidos na própria rodada.

## Regra para aprovação

A obra somente pode ser aprovada para Diagramação quando:

1. a versão aprovada na Auditoria continua válida;
2. o checklist editorial obrigatório foi concluído;
3. não existe ajuste de conteúdo na rodada atual;
4. não existem pendências editoriais bloqueantes abertas;
5. o Parecer Editorial possui conclusão;
6. o autor confirmou explicitamente a decisão editorial.

## Segurança

- cada rodada está vinculada a uma versão e hash específicos;
- rodada aprovada é imutável;
- versão aprovada é protegida no histórico de versões;
- mudanças no corpo da obra exigem nova Auditoria;
- Assistente Editorial não altera campos automaticamente;
- nenhum ISBN, registro legal ou dado de publicação é inventado ou solicitado nesta etapa;
- acesso restrito à conta proprietária da obra.

## Fora deste Sprint

Execução da diagramação, edição colaborativa externa, contratos editoriais, ISBN, ficha catalográfica, registro de direitos autorais, impressão e publicação.
