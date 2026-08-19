import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts','src/services/work-versions-service.ts','src/services/work-audit-service.ts','src/services/editorial-desk-service.ts','src/services/layout-stage-service.ts','src/services/legal-stage-service.ts','src/services/publication-stage-service.ts',
  'src/types/general-review.ts','src/types/work-versions.ts','src/types/work-audit.ts','src/types/editorial-desk.ts','src/types/layout-stage.ts','src/types/legal-stage.ts','src/types/publication-stage.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx',
  'src/components/IdentificationStage.tsx','src/components/ProjectStage.tsx','src/components/PlanningStage.tsx','src/components/DevelopmentStage.tsx','src/components/ChapterPreparationStage.tsx','src/components/ChapterResearchStage.tsx','src/components/ChapterWritingStage.tsx','src/components/ChapterRevisionStage.tsx','src/components/GeneralReviewStage.tsx','src/components/WorkVersionsStage.tsx','src/components/WorkAuditStage.tsx','src/components/EditorialDeskStage.tsx','src/components/LayoutStage.tsx','src/components/LegalStage.tsx','src/components/PublicationStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/main.tsx','src/pages/VerbumApp.tsx',
  'src/styles/verbum.css','src/styles/workspace.css','src/styles/planning-stage.css','src/styles/structure-stage.css','src/styles/development-stage.css','src/styles/identification-hom027-polish.css','src/styles/chapter-preparation.css','src/styles/chapter-research.css','src/styles/chapter-writing.css','src/styles/chapter-revision.css','src/styles/general-review.css','src/styles/work-versions.css','src/styles/work-audit.css','src/styles/editorial-desk.css','src/styles/layout-stage.css','src/styles/legal-stage.css','src/styles/publication-stage.css',
  'src/auth-profile-runtime.js','src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-initial-prelude.js','src/identification-runtime.js','src/hom027-journey-runtime.js','src/project-stage-runtime.js','src/foundation-intention-runtime.js','src/foundation-reader-result-runtime.js','src/foundation-truth-central-runtime.js','src/planning-stage-runtime.js','src/structure-direction-runtime.js','src/structure-architecture-runtime.js','src/structure-elements-runtime.js','src/structure-index-runtime.js','src/development-stage-runtime.js','src/chapter-workflow-runtime.js','src/chapter-preparation-runtime.js','src/chapter-research-runtime.js','src/chapter-writing-runtime.js','src/revision-hom012-hotfix.js','src/chapter-revision-runtime.js','src/general-review-runtime.js','src/work-versions-runtime.js','src/work-audit-runtime.js','src/editorial-desk-runtime.js','src/layout-stage-runtime.js','src/legal-stage-runtime.js','src/publication-stage-runtime.js','src/technical-runtime.js','src/dashboard-official-runtime.js','src/sidebar-profile-runtime.js','src/minhas-obras-runtime.js','src/profile-polish-runtime.js','src/vite-env.d.ts',
];
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (!contents.trim()) throw new Error(`${file} is empty`); }
function requireAll(contents, expected, label) { for (const item of expected) if (!contents.includes(item)) throw new Error(`${label} missing: ${item}`); }

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
requireAll(workspace, ['IdentificationStage','ProjectStage','PlanningStage','DevelopmentStage','GeneralReviewStage','WorkVersionsStage','WorkAuditStage','EditorialDeskStage','LayoutStage','LegalStage','PublicationStage','WorkWorkflow','WorkspaceFooter','beforeunload'], 'WorkWorkspace');

const identificationRuntime = await readFile(resolve(root, 'src/identification-runtime.js'), 'utf8');
requireAll(identificationRuntime, ['Identificação Inicial','Dados principais','Posicionamento inicial','Formato e planejamento','Salvar rascunho','Salvar e continuar','Etapa 1 de 8','Validação','Digite palavras-chave separadas por vírgula','cover_position_x','Adicionar imagem','Reposicionar'], 'Identification Initial runtime HOM-027');
const identificationPrelude = await readFile(resolve(root, 'src/identification-initial-prelude.js'), 'utf8');
requireAll(identificationPrelude, ['identification-initial','identification-initial/complete','workspace','MutationObserver','verbum-id-mobile-toggle'], 'Identification Initial bridge HOM-027');
const identificationCss = await readFile(resolve(root, 'src/styles/identification.css'), 'utf8');
requireAll(identificationCss, ['.verbum-identification-nav','.verbum-id-page-header','.verbum-id-card','.verbum-id-cover-empty','.verbum-identification-initial-footer','grid-template-columns:244px'], 'Identification Initial CSS HOM-027');
const journey027 = await readFile(resolve(root, 'src/hom027-journey-runtime.js'), 'utf8');
requireAll(journey027, ['Revisão Geral','Validação','repeat(8','versions','audit','Preparação Editorial'], 'Eight-stage journey HOM-027');
const intentionRuntime = await readFile(resolve(root, 'src/foundation-intention-runtime.js'), 'utf8');
const readerResultRuntime = await readFile(resolve(root, 'src/foundation-reader-result-runtime.js'), 'utf8');
const truthCentralRuntime = await readFile(resolve(root, 'src/foundation-truth-central-runtime.js'), 'utf8');
const structureDirectionRuntime = await readFile(resolve(root, 'src/structure-direction-runtime.js'), 'utf8');
const structureArchitectureRuntime = await readFile(resolve(root, 'src/structure-architecture-runtime.js'), 'utf8');
const structureElementsRuntime = await readFile(resolve(root, 'src/structure-elements-runtime.js'), 'utf8');
const structureIndexRuntime = await readFile(resolve(root, 'src/structure-index-runtime.js'), 'utf8');
requireAll(intentionRuntime, ['Fundação 2 de 4','Problema ou necessidade','Propósito da obra','Objetivo geral','Objetivos específicos','Assistência de coerência','Verificar coerência','Salvar rascunho','Salvar e avançar','/foundation/intention','/foundation/intention/complete','/foundation/intention/coherence'], 'Foundation Intention runtime HOM-029');
requireAll(readerResultRuntime, ['Fundação 3 de 4','Público principal','Necessidades do leitor','Transformação esperada','Diferencial da obra','Limites da obra','Editar identificação','Assistência de coerência','Salvar rascunho','Salvar e avançar','/foundation/reader-result','/foundation/reader-result/complete','/foundation/reader-result/coherence'], 'Foundation Reader Result runtime HOM-030');
requireAll(truthCentralRuntime, ['Fundação 4 de 4','Tese principal','Frase que resume a obra','Conferência da Fundação','Verificar coerência completa','Próxima etapa: Estrutura da Obra','Concluir Fundação','/foundation/truth-central','generate-thesis','generate-phrases','coherence'], 'Foundation Truth Central runtime HOM-031');
requireAll(structureDirectionRuntime, ['Estrutura 1 de 4','Direção','Arquitetura','Elementos','Índice Provisório','Fundação concluída','Eixo da obra','Fio condutor','Ordem teológica','Ponto de partida','Ponto de chegada','Movimento da obra','Verificar coerência','Salvar e avançar','/structure/direction','/complete','/coherence'], 'Structure Direction runtime HOM-032');
requireAll(structureArchitectureRuntime, ['Estrutura 2 de 4','Divida a obra em partes coerentes','Gerar arquitetura da obra','Comparar com estrutura atual','Ver conteúdos preservados','Partes da obra','Detalhes da Parte','Movimento relacionado','Assistência de arquitetura','Analisar arquitetura','Salvar e avançar','/structure/architecture','/complete','/generate','/analyze'], 'Structure Architecture runtime HOM-033');
requireAll(structureElementsRuntime, ['Estrutura 3 de 4','Organize os elementos iniciais e finais','Elementos iniciais','Elementos finais','Carta aos Leitores','Vinculada à Fundação','Conteúdos anteriores preservados','Comparar alterações','Assistência editorial','Analisar elementos','Salvar e avançar','/structure/elements','/complete','/analyze'], 'Structure Elements runtime HOM-034');
requireAll(structureIndexRuntime, ['Estrutura 4 de 4','Organize o índice antes de criar os capítulos','Gerar índice provisório','Gerar sugestões','Comparar com índice atual','Hierarquia da obra','Revisão do índice','Estrutura pronta','Prévia para Capítulos','Nenhum capítulo será alterado nesta tela','Concluir Estrutura','/structure/index','/complete','/generate','/analyze'], 'Structure Index runtime HOM-035');

const planning = await readFile(resolve(root, 'src/components/PlanningStage.tsx'), 'utf8');
requireAll(planning, ['getWorkDevelopment','generatedChapterIds.length','Prévia somente leitura','Conclua o Planejamento para editar os capítulos'], 'PlanningStage compatibility');
const planningRuntime = await readFile(resolve(root, 'src/planning-stage-runtime.js'), 'utf8');
requireAll(planningRuntime, ['function updateHeader','Estrutura da Obra','Fio condutor e movimento da obra','Observações estruturais','Elementos iniciais','Corpo da obra','Elementos finais','Capítulo pai','chapter-sync-preview','Gerar ou sincronizar capítulos','Confirmar sincronização','Continuar para Capítulos','Conteúdos anteriores preservados','Salvando...','Alterações salvas','Erro ao salvar'], 'Structure runtime HOM-025');
if (planningRuntime.includes("data-generate-preview]').onclick=function(){api('/books/")) throw new Error('Structure synchronization must display preview before mutations');
const planningCss = await readFile(resolve(root, 'src/styles/planning-stage.css'), 'utf8'); requireAll(planningCss, ['.verbum-planning-stage','.verbum-outline-item','.verbum-project-progress'], 'Planning compatibility CSS');
const structureCss = await readFile(resolve(root, 'src/styles/structure-stage.css'), 'utf8'); requireAll(structureCss, ['.verbum-structure-stage','.verbum-structure-group','.verbum-outline-parent','.verbum-sync-preview','.verbum-structure-progress-stats','@media'], 'Structure CSS HOM-025');

const development = await readFile(resolve(root, 'src/components/DevelopmentStage.tsx'), 'utf8');
requireAll(development, ['Capítulos','Continuar trabalhando','Continuar capítulo','Continuar para Revisão da Obra','ChapterPreparationStage','ChapterResearchStage','ChapterWritingStage','ChapterRevisionStage','Preparação','Pesquisa','Redação','Revisão'], 'Central de Capítulos HOM-026');
const developmentRuntime = await readFile(resolve(root, 'src/development-stage-runtime.js'), 'utf8');
requireAll(developmentRuntime, ['<h2>Capítulos</h2>','Continuar trabalhando','Continuar capítulo','Voltar para Estrutura','Continuar para Revisão da Obra','A Central de Capítulos não recria o índice','structure-sync','Comparação antes da sincronização','Nenhum texto já escrito será substituído sem confirmação'], 'Development runtime HOM-036');
const developmentCss = await readFile(resolve(root, 'src/styles/development-stage-base.css'), 'utf8');
requireAll(developmentCss, ['.verbum-chapters-summary','.verbum-chapters-continue','.verbum-chapter-part','.verbum-chapters-footer-note','.verbum-chapter-sync','.verbum-chapter-sync-comparison'], 'Central de Capítulos CSS HOM-036');

const stages = [
  ['ChapterPreparationStage.tsx',['Direção do Capítulo','Estrutura do Capítulo','Pesquisa necessária','Intenção espiritual','Aprofundar preparação','Concluir Preparação e ir para Pesquisa']],
  ['ChapterResearchStage.tsx',['Direção da Pesquisa','Central de Pesquisa','Banco de Ideias do capítulo','Concluir Pesquisa']],
  ['ChapterWritingStage.tsx',['Assistente de Escrita','Modo Foco','Salvar agora','Concluir Redação']],
  ['ChapterRevisionStage.tsx',['Direção original','Fontes e Citações','Pendências da Revisão','Concluir Revisão']],
  ['GeneralReviewStage.tsx',['Revisão Geral da Obra','Ler obra completa','Pendências da Revisão Geral','Concluir Revisão Geral']],
  ['WorkVersionsStage.tsx',['Controle de Versões','Alterações não versionadas','Versão para Auditoria','Concluir Controle de Versões']],
  ['WorkAuditStage.tsx',['Auditoria da Obra','Versão em Auditoria','Relatório de Auditoria','Aprovar Auditoria']],
  ['EditorialDeskStage.tsx',['Mesa Editorial','Ficha Editorial da Obra','Briefing de Capa','Parecer Editorial','Aprovar para Diagramação']],
  ['LayoutStage.tsx',['Diagramação da Obra','Modelo visual','Prévia do miolo','PDF de prova','Concluir Diagramação']],
  ['LegalStage.tsx',['Trâmites Legais da Obra','ISBN por formato','Ficha Catalográfica','Conteúdos de terceiros','Documentos','Arquivos finais','Assistente Legal-Editorial','Concluir Trâmites Legais']],
  ['PublicationStage.tsx',['Publicação da Obra','Pacote Final da Edição','Metadados da Publicação','Estratégia de Preço','Canais de Publicação','Plano de Lançamento','Assistente de Publicação','Concluir Publicação','Obra Publicada']],
];
for (const [file, expected] of stages) requireAll(await readFile(resolve(root, `src/components/${file}`), 'utf8'), expected, file);

const runtimes = [
  ['chapter-preparation-runtime.js',['/preparation','/preparation/complete','Direção do Capítulo','Estrutura do Capítulo','Pesquisa necessária','Aprofundar preparação','Concluir Preparação e ir para Pesquisa']],
  ['chapter-research-runtime.js',['/research','/research/sources','/research/complete','Central de Pesquisa']],
  ['chapter-writing-runtime.js',['/writing','/writing/complete','/writing/assist','Assistente de Escrita']],
  ['chapter-revision-runtime.js',['/revision','/revision/issues','/revision/complete','Assistente de Revisão']],
  ['general-review-runtime.js',['/general-review','/general-review/reading','/general-review/complete','Revisão Geral da Obra']],
  ['work-versions-runtime.js',['/versions-stage','/versions-stage/compare','/audit-baseline','Controle de Versões']],
  ['work-audit-runtime.js',['/audit-stage','/audit-stage/findings','/audit-stage/report','Aprovar Auditoria']],
  ['editorial-desk-runtime.js',['/editorial-desk','/editorial-desk/adjustments','/editorial-desk/complete','Mesa Editorial']],
  ['layout-stage-runtime.js',['/layout-stage','/layout-stage/preview','/layout-stage/proofs','Diagramação da Obra']],
  ['legal-stage-runtime.js',['/legal-stage','/legal-stage/documents','/legal-stage/third-party','/legal-stage/issues','/legal-stage/proofs','/legal-stage/assist','/legal-stage/complete','Trâmites Legais da Obra']],
  ['publication-stage-runtime.js',['/publication-stage','/publication-stage/channels','/publication-stage/tasks','/publication-stage/assist','/publication-stage/complete','Publicação da Obra','Pacote Final da Edição','Canais de Publicação','Concluir Publicação','Obra Publicada']],
];
for (const [file, expected] of runtimes) requireAll(await readFile(resolve(root, `src/${file}`), 'utf8'), expected, file);

const hom012 = await readFile(resolve(root, 'src/revision-hom012-hotfix.js'), 'utf8'); requireAll(hom012, ['latestRevision','pendingIssueCount','data-hom012-issue-status','data-hom012-issue-delete','reconcileChecklist','_verbum_nocache'], 'Revision HOM-012 rehydration');
const legalService = await readFile(resolve(root, 'src/services/legal-stage-service.ts'), 'utf8'); requireAll(legalService, ['getLegalStage','saveLegalStage','createLegalDocument','createThirdPartyItem','createLegalIssue','registerLegalProof','assistLegalStage','completeLegalStage'], 'Legal stage service');
const publicationService = await readFile(resolve(root, 'src/services/publication-stage-service.ts'), 'utf8'); requireAll(publicationService, ['getPublicationStage','savePublicationStage','createPublicationChannel','updatePublicationChannel','createPublicationTask','registerPublicationUpdate','assistPublicationStage','completePublicationStage'], 'Publication stage service');
const legalCss = await readFile(resolve(root, 'src/styles/legal-stage.css'), 'utf8'); requireAll(legalCss, ['.verbum-legal-grid','.verbum-legal-card','.verbum-legal-isbn','.verbum-legal-list','.verbum-legal-progress','.verbum-legal-history','@media'], 'Legal stage CSS');
const publicationCss = await readFile(resolve(root, 'src/styles/publication-stage.css'), 'utf8'); requireAll(publicationCss, ['.verbum-publication-grid','.verbum-publication-card','.verbum-publication-stats','.verbum-publication-list','.verbum-publication-progress','.verbum-publication-celebration','@media'], 'Publication stage CSS');
const header = await readFile(resolve(root, 'src/components/Header.tsx'), 'utf8'); if (header.includes('Área atual')) throw new Error('Header must not render Área atual');
const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8'); if (!sidebar.includes('Minhas Obras')) throw new Error('Sidebar must use Minhas Obras');
const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8'); requireAll(buildJs, ['identification-initial-prelude.js','hom027-journey-runtime.js','foundation-reader-result-runtime.js','foundation-truth-central-runtime.js','structure-direction-runtime.js','structure-architecture-runtime.js','structure-elements-runtime.js','structure-index-runtime.js','identification-hom027-polish.css','layout-stage-runtime.js','legal-stage-runtime.js','publication-stage-runtime.js','script.async=false'], 'Static JS build');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8'); requireAll(buildCss, ['layout-stage.css','legal-stage.css','publication-stage.css','profile-polish.css'], 'Static CSS build');
const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`); }
console.log('Frontend homologation checks passed for Verbum Studio through HOM-036');
