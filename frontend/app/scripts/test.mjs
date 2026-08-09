import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx',
  'src/components/QuickActions.tsx','src/components/StatCard.tsx','src/components/RecentActivity.tsx','src/components/EmptyState.tsx',
  'src/components/ProjectDialog.tsx','src/components/BookDialog.tsx','src/components/BookCard.tsx',
  'src/components/WorkHeader.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/pages/VerbumApp.tsx','src/main.tsx',
  'src/styles/verbum.css','src/styles/library.css','src/styles/workspace.css','src/static-runtime.js',
];
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
for (const expected of ['getHealth()','getCurrentUser()','getLibrary()','getWorkWorkspace','verbum_work','verbum_stage','<WorkWorkspace','<WorkHeader','Carregando Verbum Studio']) {
  if (!app.includes(expected)) throw new Error(`VerbumApp missing Sprint 04 requirement: ${expected}`);
}

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
for (const expected of ['WorkWorkflow','WorkspaceFooter','beforeunload','Etapa anterior','stageDescriptions']) {
  if (!workspace.includes(expected)) throw new Error(`WorkWorkspace missing: ${expected}`);
}

const workHeader = await readFile(resolve(root, 'src/components/WorkHeader.tsx'), 'utf8');
for (const expected of ['ETAPA ATUAL','IMO','RME','PROGRESSO','CAPÍTULOS','PALAVRAS','ÚLTIMA EDIÇÃO']) {
  if (!workHeader.includes(expected)) throw new Error(`WorkHeader missing metric: ${expected}`);
}

const workflow = await readFile(resolve(root, 'src/components/WorkWorkflow.tsx'), 'utf8');
for (const expected of ['completed','locked','in_progress','aria-current']) {
  if (!workflow.includes(expected)) throw new Error(`WorkWorkflow missing state support: ${expected}`);
}

const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8');
for (const expected of ['Painel','Mesa de Trabalho','Obras','Base de Conhecimento','Calendário Editorial','Relatórios','Verbum Cloud','Lixeira']) {
  if (!sidebar.includes(expected)) throw new Error(`Sidebar missing global item: ${expected}`);
}

const bookCard = await readFile(resolve(root, 'src/components/BookCard.tsx'), 'utf8');
if (!bookCard.includes('Abrir obra') || !bookCard.includes('onOpen')) throw new Error('BookCard must open the work workspace');

const libraryService = await readFile(resolve(root, 'src/services/library-service.ts'), 'utf8');
for (const expected of ['/library','/projects','/books','/workspace','getWorkWorkspace']) {
  if (!libraryService.includes(expected)) throw new Error(`Library service missing: ${expected}`);
}

const workspaceCss = await readFile(resolve(root, 'src/styles/workspace.css'), 'utf8');
for (const expected of ['.verbum-work-header','.verbum-workflow','.verbum-stage-content','.verbum-workspace-footer','.verbum-nav-group','@media']) {
  if (!workspaceCss.includes(expected)) throw new Error(`Workspace CSS missing: ${expected}`);
}

const runtime = await readFile(resolve(root, 'src/static-runtime.js'), 'utf8');
for (const expected of ['/health','/me','/library','/workspace','verbum_work','verbum_stage','Abrir obra','Controle de Versões','Trâmites Legais','X-WP-Nonce']) {
  if (!runtime.includes(expected)) throw new Error(`Static runtime missing Sprint 04 requirement: ${expected}`);
}

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
if (!buildJs.includes('frontend/app/src/static-runtime.js')) throw new Error('Static JS build must load runtime');
for (const expected of ['verbum.css','library.css','workspace.css']) {
  if (!buildCss.includes(expected)) throw new Error(`Static CSS build missing: ${expected}`);
}

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}
console.log('Frontend Sprint 04 workspace static checks passed');
