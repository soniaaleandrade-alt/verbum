# Sprint 15 — Auditoria da Obra

**Versão:** 2.1.0
**Etapa da obra:** 7 de 11
**Fluxo:** Revisão Geral ✓ → Controle de Versões ✓ → Auditoria → Mesa Editorial

## Objetivo

Auditar formalmente uma versão congelada da obra antes da Mesa Editorial, verificando integridade do snapshot, estrutura, conteúdo pendente, fontes, referências, consistência, elementos editoriais e preparação final.

## Entregas

- Auditoria sempre vinculada à `Versão para Auditoria` escolhida no Controle de Versões;
- criação automática de rodadas de Auditoria por versão, preservando histórico;
- versão auditada imutável durante a rodada;
- alerta quando a obra de trabalho possui alterações posteriores à baseline;
- painel com achados, avisos, pendências, críticos e progresso;
- categorias: Integridade, Estrutura Editorial, Conteúdo, Fontes e Referências, Consistência, Elementos Pré/Pós-textuais, Preparação Editorial e Conferência Doutrinal;
- verificações automáticas de hash, existência de capítulos, sequência, duplicidades, capítulos vazios e marcadores `TODO`, `???`, `[completar]`, `[inserir ...]`, `[revisar]` e `XXXXX`;
- Introdução Geral e Conclusão Geral tratadas como elementos obrigatórios; Prefácio, Apresentação e Nota do Autor como opcionais;
- snapshot das fontes efetivamente utilizadas e situação de verificação das referências;
- achados manuais com categoria, severidade, recomendação e capítulo relacionado;
- status Aberto, Em análise, Resolvido e Ignorado com justificativa obrigatória;
- Assistente de Auditoria contextual, sem alteração automática da obra, sem invenção de fontes e sem declaração automática de aprovação editorial ou doutrinal;
- relatório consolidado da rodada com versão, hash, resumo, achados e resultado;
- checklist final e confirmação explícita do autor;
- aprovação vinculada ao ID e hash exatos da versão;
- versão aprovada protegida e marcada com data de aprovação;
- conclusão da Auditoria liberando Mesa Editorial;
- endpoints REST próprios, runtime de produção, interface React, estilos e isolamento por conta.

## Resultados possíveis

- **Em andamento** — não há bloqueio atual, mas a rodada ainda não foi concluída;
- **Requer correções** — há pendências ou achados críticos em aberto;
- **Aprovada** — checklist completo, relatório conferido, confirmação final e nenhum bloqueio obrigatório.

## Regra para aprovação

A Auditoria só pode ser aprovada quando:

1. a baseline selecionada no Controle de Versões continua íntegra;
2. não há achados críticos ou pendências obrigatórias em aberto;
3. estrutura, capítulos, marcadores, fontes, citações, terminologia e elementos editoriais foram conferidos;
4. o relatório da Auditoria foi gerado e conferido;
5. o autor confirmou explicitamente a versão auditada.

## Correções após achados

A versão auditada nunca é alterada pela Auditoria. Se o autor corrigir a obra de trabalho, deve voltar ao Controle de Versões, criar uma nova versão, marcá-la como `Versão para Auditoria` e iniciar uma nova rodada. Rodadas anteriores permanecem preservadas.

## Segurança

- Auditoria vinculada a uma versão e hash específicos;
- snapshot e fontes da rodada permanecem congelados;
- achados não alteram conteúdo;
- achados ignorados exigem justificativa;
- relatório e histórico das rodadas são preservados;
- versão aprovada fica protegida;
- acesso restrito à conta proprietária da obra.

## Fora deste Sprint

Aprovação por editor externo, pareceristas, comentários colaborativos, assinatura eletrônica, parecer editorial, Mesa Editorial, Diagramação, Trâmites Legais e Publicação.
