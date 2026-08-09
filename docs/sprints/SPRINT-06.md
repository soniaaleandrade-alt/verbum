# Sprint 06 — Projeto da Obra

**Projeto:** Verbum Studio — Sistema Operacional para Escritores  
**Módulo:** Workspace da Obra  
**Etapa:** Projeto da Obra  
**Versão:** 1.0  
**Prioridade:** Alta  
**Status:** Implementado em branch, aguardando PR/merge  
**Dependência:** Sprint 05 — Identificação da Obra

## Objetivo

Implementar a segunda etapa funcional do workflow da obra. O Projeto da Obra responde à pergunta: **“Por que esta obra existe, para quem ela existe e o que pretende produzir no leitor?”**

## Fluxo

Identificação concluída → Projeto da Obra → Salvar → Concluir Projeto da Obra → Planejamento liberado.

## Blocos funcionais

### Propósito da Obra

- Objetivo Geral
- Objetivos Específicos, com inclusão, edição, remoção e reordenação
- Finalidade da Obra

### Público e Impacto

- Público-Alvo aprofundado
- Benefícios Esperados para o Leitor
- Transformação Esperada

### Identidade da Obra

- Mensagem Central
- Diferenciais da Obra
- Proposta de Valor
- Palavra-chave da Obra, usada para indexação interna e fora do checklist

### Inspiração

- Motivação Pessoal
- Versículo Inspirador
- Frase Norteadora

## Progresso da etapa

O checklist é automático e possui 12 critérios:

1. Objetivo geral
2. Objetivos específicos
3. Finalidade
4. Público
5. Benefícios
6. Transformação
7. Mensagem central
8. Diferenciais
9. Proposta de valor
10. Motivação
11. Versículo
12. Frase norteadora

Objetivos específicos são considerados concluídos quando existe pelo menos um item válido. O percentual é calculado automaticamente pelo preenchimento dos 12 critérios.

## Salvamento e conclusão

**Salvar** persiste o conteúdo sem concluir a etapa. O Workspace mantém os estados Salvo, Alterações não salvas, Salvando e Erro ao salvar e protege contra saída acidental.

A etapa somente pode ser concluída com 12/12 critérios. Na conclusão:

- Projeto da Obra passa para concluído;
- Planejamento é liberado e se torna a etapa atual;
- os dados continuam editáveis;
- retirar posteriormente um requisito recalcula o progresso e devolve Projeto da Obra para andamento sem apagar dados das etapas posteriores.

## Persistência

Os dados são vinculados exclusivamente à obra. Objetivos específicos são persistidos como coleção ordenada, e não como um único texto.

## API

- `GET /books/{id}/project-stage`
- `PATCH /books/{id}/project-stage`
- `POST /books/{id}/project-stage/complete`

Todos os endpoints preservam autenticação WordPress, nonce REST, capacidades e validação de propriedade da obra.

## Fora do escopo

Não fazem parte deste Sprint: Planejamento, estrutura inicial, índice, capítulos, pesquisa, banco bíblico, Catecismo, Magistério, citações, IA, redação, revisão e etapas de publicação.

## Critérios de aceite

- os quatro blocos da referência Bolt são funcionais;
- múltiplos objetivos específicos podem ser criados, editados, removidos e reordenados;
- os 12 itens do checklist são automáticos;
- progresso é calculado corretamente;
- dados permanecem após atualizar a página;
- Salvar e proteção de alterações não salvas funcionam;
- conclusão só ocorre em 12/12;
- Planejamento é liberado após conclusão;
- layout funciona em desktop, tablet e mobile;
- CI verde.

## Próximo Sprint

**Sprint 07 — Planejamento da Obra**.

## Registro de implementação

**Branch:** `sprint-06-projeto-da-obra`  
**PR:** A definir  
**Commit de merge:** A definir  
**CI:** A definir
