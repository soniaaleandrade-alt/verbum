import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx',
  'src/components/QuickActions.tsx','src/components/StatCard.tsx','src/components/RecentActivity.tsx','src/components/EmptyState.tsx',
  'src/components/ProjectDialog.tsx','src/components/BookDialog.tsx','src/components/BookCard.tsx',
  'src/components/WorkHeader.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx','src/components/IdentificationStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/pages/VerbumApp.tsx','src/main.tsx',
  'src/styles/verbum.css','src/styles/library.css','src/styles/workspace.css','src/styles/identification.css',
  'src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-runtime.js','src/vite-env.d.ts',
];
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
for (const expected of ['getHealth()','getCurrentUser()','getLibrary()','getWorkWorkspace','verbum_work','verbum_stage','<WorkWorkspace','<WorkHeader','identification.css']) {
  if (!app.includes(expected)) throw new Error(`VerbumApp missing workspace requirement: ${expected}`);
}

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
for (const expected of ['IdentificationStage','WorkWorkflow','WorkspaceFooter','beforeunload','onWorkspaceChange','onPersisted']) {
  if (!workspace.includes(expected)) throw new Error(`WorkWorkspace missing Sprint 05 integration: ${expected}`);
}

const identification = await readFile(resolve(root, 'src/components/IdentificationStage.tsx'), 'utf8');
for (const expected of [
  'Título','Subtítulo','Status','Gênero','Idioma','Público-alvo','Sinopse','Palavras-chave','Capa da obra','Cor da obra',
  'Progresso da Etapa','Definir título','Enviar capa da obra','10 * 1024 * 1024','completeIdentification','uploadBookCover','removeBookCover',
]) {
  if (!identification.includes(expected)) throw new Error(`IdentificationStage missing: ${expected}`);
}

const workspaceFooter = await readFile(resolve(root, 'src/components/WorkspaceFooter.tsx'), 'utf8');
for (const expected of ['Etapa anterior','Salvar','Salvar e continuar','Alterações não salvas','Erro ao salvar','previousDisabled ?? !canGoBack']) {
  if (!workspaceFooter.includes(expected)) throw new Error(`WorkspaceFooter missing: ${expected}`);
}

const workHeader = await readFile(resolve(root, 'src/components/WorkHeader.tsx'), 'utf8');
for (const expected of ['ETAPA ATUAL','IMO','RME','PROGRESSO','CAPÍTULOS','PALAVRAS','ÚLTIMA EDIÇÃO','coverUrl']) {
  if (!workHeader.includes(expected)) throw new Error(`WorkHeader missing metric/navigation: ${expected}`);
}

const workflow = await readFile(resolve(root, 'src/components/WorkWorkflow.tsx'), 'utf8');
for (const expected of ['completed','locked','is-${step.status}','aria-current']) {
  if (!workflow.includes(expected)) throw new Error(`WorkWorkflow missing state support: ${expected}`);
}

const libraryService = await readFile(resolve(root, 'src/services/library-service.ts'), 'utf8');
for (const expected of ['/workspace','/identification','/identification/complete','/cover','saveIdentification','uploadBookCover','removeBookCover']) {
  if (!libraryService.includes(expected)) throw new Error(`Library service missing Sprint 05 endpoint: ${expected}`);
}

const apiClient = await readFile(resolve(root, 'src/services/api-client.ts'), 'utf8');
for (const expected of ['FormData','DELETE','apiDelete','X-WP-Nonce']) {
  if (!apiClient.includes(expected)) throw new Error(`API client missing Sprint 05 transport support: ${expected}`);
}

const identificationCss = await readFile(resolve(root, 'src/styles/identification.css'), 'utf8');
for (const expected of ['.verbum-identification-layout','.verbum-identification-progress','.verbum-color-palette','.verbum-cover-dropzone','@media']) {
  if (!identificationCss.includes(expected)) throw new Error(`Identification CSS missing: ${expected}`);
}

const runtime = await readFile(resolve(root, 'src/static-runtime.js'), 'utf8');
for (const expected of ['/health','/me','/library','/workspace','verbum_work','verbum_stage','Abrir obra','Controle de Versões','Trâmites Legais','X-WP-Nonce']) {
  if (!runtime.includes(expected)) throw new Error(`Static runtime missing workspace requirement: ${expected}`);
}

const identificationRuntime = await readFile(resolve(root, 'src/identification-runtime.js'), 'utf8');
for (const expected of ['/identification','/identification/complete','/cover','MutationObserver','Progresso da Etapa','Capa da obra','Cor da obra','beforeunload']) {
  if (!identificationRuntime.includes(expected)) throw new Error(`Identification runtime missing: ${expected}`);
}

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
for (const expected of ['static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js']) {
  if (!buildJs.includes(expected)) throw new Error(`Static JS build must load: ${expected}`);
}
for (const expected of ['verbum.css','library.css','workspace.css','identification.css']) {
  if (!buildCss.includes(expected)) throw new Error(`Static CSS build missing: ${expected}`);
}

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}
console.log('Frontend Sprint 05 Identification static checks passed');
