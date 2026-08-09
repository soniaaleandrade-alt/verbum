# Sprint 05 — Identificação da Obra

**Projeto:** Verbum Studio — Sistema Operacional para Escritores
**Módulo:** Workspace da Obra
**Etapa:** Identificação
**Versão:** 2.0
**Prioridade:** Alta
**Status:** Implementado em branch, aguardando PR/merge
**Dependência:** Sprint 04 — Workspace da Obra e Workflow Editorial

## Objetivo

Implementar a primeira etapa funcional do workflow da obra. A Identificação responde à pergunta **“Qual é esta obra?”** e trabalha sobre o mesmo registro criado no Banco de Obras, sem duplicação de dados.

## Fluxo

Banco de Obras → Abrir obra → Identificação → Salvar → Concluir Identificação → Projeto da Obra liberado.

## Campos oficiais

- Título
- Subtítulo
- Status editorial
- Gênero
- Idioma
- Público-alvo
- Sinopse
- Palavras-chave
- Capa da obra
- Cor da obra

## Capa

A capa é armazenada na biblioteca de mídia do WordPress e vinculada à obra. São aceitos JPG, JPEG, PNG e WebP, com limite máximo de 10 MB e respeito ao limite configurado no WordPress. A interface permite enviar, substituir, arrastar arquivo e remover a capa da obra sem apagar automaticamente o arquivo da biblioteca de mídia.

## Cor da obra

A interface utiliza uma paleta editorial predefinida com dez cores. A cor poderá ser reutilizada em cards, calendário, cronograma e relatórios futuros.

## Progresso da etapa

A Identificação possui 10 critérios automáticos. Cada critério vale 10%:

1. Definir título
2. Definir subtítulo
3. Escrever sinopse
4. Definir palavras-chave
5. Definir status
6. Definir gênero
7. Definir idioma
8. Definir público-alvo
9. Escolher cor da obra
10. Enviar capa da obra

O checklist é calculado pelo sistema; o usuário não marca itens manualmente.

## Salvamento

**Salvar** persiste os dados sem concluir a etapa. O Workspace apresenta os estados Salvo, Alterações não salvas, Salvando e Erro ao salvar. Alterações não salvas são protegidas ao sair da página.

## Conclusão

A Identificação só pode ser concluída com 10/10 itens preenchidos. Ao concluir:

- Identificação passa para concluída;
- Projeto da Obra é liberado;
- a etapa atual passa para Projeto da Obra;
- os dados permanecem editáveis;
- remover posteriormente um requisito concluído devolve a Identificação para andamento sem apagar dados das etapas futuras.

## Integrações

Após salvar, título, subtítulo, capa, cor e última edição são refletidos no cabeçalho do Workspace e os dados da obra permanecem sincronizados com o Banco de Obras.

## Segurança

Todos os endpoints utilizam autenticação WordPress, nonce REST, capacidades existentes e validação de propriedade da obra/projeto.

## Fora do escopo

Não fazem parte deste Sprint: objetivo geral, objetivos específicos, tese, pergunta central, estrutura inicial, índice, capítulos, pesquisa, redação, revisão, ISBN, ficha catalográfica, trâmites legais, publicação ou IA.

## Critérios de aceite

- formulário funcional dentro do Workspace;
- persistência após atualização da página;
- checklist automático 0–100%;
- upload, substituição e remoção de capa;
- paleta de cor;
- Salvar funcional;
- proteção de alterações não salvas;
- conclusão apenas em 100%;
- liberação de Projeto da Obra;
- atualização do cabeçalho e Banco de Obras;
- responsividade desktop/tablet/mobile;
- CI verde.

## Próximo Sprint

**Sprint 06 — Projeto da Obra**, responsável pela fundação conceitual e editorial: objetivo geral, objetivos específicos, finalidade, problema, proposta, público aprofundado, transformação esperada, diferencial e posicionamento editorial.

## Registro de implementação

**Branch:** `sprint-05-identificacao-da-obra`
**PR:** A definir
**Commit de merge:** A definir
**CI:** A definir
