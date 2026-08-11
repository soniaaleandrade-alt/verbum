import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts','src/services/work-versions-service.ts','src/services/work-audit-service.ts','src/services/editorial-desk-service.ts','src/services/layout-stage-service.ts','src/services/legal-stage-service.ts',
  'src/types/general-review.ts','src/types/work-versions.ts','src/types/work-audit.ts','src/types/editorial-desk.ts','src/types/layout-stage.ts','src/types/legal-stage.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx',
  'src/components/IdentificationStage.tsx','src/components/ProjectStage.tsx','src/components/PlanningStage.tsx','src/components/DevelopmentStage.tsx','src/components/ChapterPreparationStage.tsx','src/components/ChapterResearchStage.tsx','src/components/ChapterWritingStage.tsx','src/components/ChapterRevisionStage.tsx','src/components/GeneralReviewStage.tsx','src/components/WorkVersionsStage.tsx','src/components/WorkAuditStage.tsx','src/components/EditorialDeskStage.tsx','src/components/LayoutStage.tsx','src/components/LegalStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/main.tsx','src/pages/VerbumApp.tsx',
  'src/styles/verbum.css','src/styles/workspace.css','src/styles/planning-stage.css','src/styles/development-stage.css','src/styles/chapter-preparation.css','src/styles/chapter-research.css','src/styles/chapter-writing.css','src/styles/chapter-revision.css','src/styles/general-review.css','src/styles/work-versions.css','src/styles/work-audit.css','src/styles/editorial-desk.css','src/styles/layout-stage.css','src/styles/legal-stage.css',
  'src/auth-profile-runtime.js','src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-runtime.js','src/project-stage-runtime.js','src/planning-stage-runtime.js','src/development-stage-runtime.js','src/chapter-workflow-runtime.js','src/chapter-preparation-runtime.js','src/chapter-research-runtime.js','src/chapter-writing-runtime.js','src/chapter-revision-runtime.js','src/general-review-runtime.js','src/work-versions-runtime.js','src/work-audit-runtime.js','src/editorial-desk-runtime.js','src/layout-stage-runtime.js','src/legal-stage-runtime.js','src/technical-runtime.js','src/dashboard-official-runtime.js','src/sidebar-profile-runtime.js','src/minhas-obras-runtime.js','src/profile-polish-runtime.js','src/vite-env.d.ts',
];
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (!contents.trim()) throw new Error(`${file} is empty`); }
function requireAll(contents, expected, label) { for (const item of expected) if (!contents.includes(item)) throw new Error(`${label} missing: ${item}`); }

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
requireAll(workspace, ['IdentificationStage','ProjectStage','PlanningStage','DevelopmentStage','GeneralReviewStage','WorkVersionsStage','WorkAuditStage','EditorialDeskStage','LayoutStage','LegalStage','WorkWorkflow','WorkspaceFooter','beforeunload'], 'WorkWorkspace');

const development = await readFile(resolve(root, 'src/components/DevelopmentStage.tsx'), 'utf8');
requireAll(development, ['Desenvolvimento da Obra','ChapterPreparationStage','ChapterResearchStage','ChapterWritingStage','ChapterRevisionStage','Preparação','Pesquisa','Redação','Revisão','Abrir capítulo'], 'DevelopmentStage');

const stages = [
  ['ChapterPreparationStage.tsx',['Identidade do Capítulo','Direção da Escrita','Concluir Preparação']],
  ['ChapterResearchStage.tsx',['Direção da Pesquisa','Central de Pesquisa','Banco de Ideias do capítulo','Concluir Pesquisa']],
  ['ChapterWritingStage.tsx',['Assistente de Escrita','Modo Foco','Salvar agora','Concluir Redação']],
  ['ChapterRevisionStage.tsx',['Direção original','Fontes e Citações','Pendências da Revisão','Concluir Revisão']],
  ['GeneralReviewStage.tsx',['Revisão Geral da Obra','Ler obra completa','Pendências da Revisão Geral','Concluir Revisão Geral']],
  ['WorkVersionsStage.tsx',['Controle de Versões','Alterações não versionadas','Versão para Auditoria','Concluir Controle de Versões']],
  ['WorkAuditStage.tsx',['Auditoria da Obra','Versão em Auditoria','Relatório de Auditoria','Aprovar Auditoria']],
  ['EditorialDeskStage.tsx',['Mesa Editorial','Ficha Editorial da Obra','Briefing de Capa','Parecer Editorial','Aprovar para Diagramação']],
  ['LayoutStage.tsx',['Diagramação da Obra','Modelo visual','Prévia do miolo','PDF de prova','Concluir Diagramação']],
  ['LegalStage.tsx',['Trâmites Legais da Obra','ISBN por formato','Ficha Catalográfica','Conteúdos de terceiros','Central de Documentos','Arquivos finais','Assistente Legal-Editorial','Concluir Trâmites Legais']],
];
for (const [file, expected] of stages) requireAll(await readFile(resolve(root, `src/components/${file}`), 'utf8'), expected, file);

const runtimes = [
  ['chapter-preparation-runtime.js',['/preparation','/preparation/complete','Concluir Preparação']],
  ['chapter-research-runtime.js',['/research','/research/sources','/research/complete','Central de Pesquisa']],
  ['chapter-writing-runtime.js',['/writing','/writing/complete','/writing/assist','Assistente de Escrita']],
  ['chapter-revision-runtime.js',['/revision','/revision/issues','/revision/complete','Assistente de Revisão']],
  ['general-review-runtime.js',['/general-review','/general-review/reading','/general-review/complete','Revisão Geral da Obra']],
  ['work-versions-runtime.js',['/versions-stage','/versions-stage/compare','/audit-baseline','Controle de Versões']],
  ['work-audit-runtime.js',['/audit-stage','/audit-stage/findings','/audit-stage/report','Aprovar Auditoria']],
  ['editorial-desk-runtime.js',['/editorial-desk','/editorial-desk/adjustments','/editorial-desk/complete','Mesa Editorial']],
  ['layout-stage-runtime.js',['/layout-stage','/layout-stage/preview','/layout-stage/proofs','Diagramação da Obra']],
  ['legal-stage-runtime.js',['/legal-stage','/legal-stage/documents','/legal-stage/third-party','/legal-stage/issues','/legal-stage/proofs','/legal-stage/assist','/legal-stage/complete','Trâmites Legais da Obra']],
];
for (const [file, expected] of runtimes) requireAll(await readFile(resolve(root, `src/${file}`), 'utf8'), expected, file);

const legalService = await readFile(resolve(root, 'src/services/legal-stage-service.ts'), 'utf8');
requireAll(legalService, ['getLegalStage','saveLegalStage','createLegalDocument','createThirdPartyItem','createLegalIssue','registerLegalProof','assistLegalStage','completeLegalStage'], 'Legal stage service');
const legalCss = await readFile(resolve(root, 'src/styles/legal-stage.css'), 'utf8');
requireAll(legalCss, ['.verbum-legal-grid','.verbum-legal-card','.verbum-legal-isbn','.verbum-legal-list','.verbum-legal-progress','.verbum-legal-history','@media'], 'Legal stage CSS');

const header = await readFile(resolve(root, 'src/components/Header.tsx'), 'utf8'); if (header.includes('Área atual')) throw new Error('Header must not render Área atual');
const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8'); if (!sidebar.includes('Minhas Obras')) throw new Error('Sidebar must use Minhas Obras');
const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8'); requireAll(buildJs, ['layout-stage-runtime.js','legal-stage-runtime.js','script.async=false'], 'Static JS build');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8'); requireAll(buildCss, ['layout-stage.css','legal-stage.css','profile-polish.css'], 'Static CSS build');
const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`); }
console.log('Frontend Sprint 18 Trâmites Legais checks passed');
