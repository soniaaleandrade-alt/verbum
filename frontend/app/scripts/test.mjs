import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx',
  'src/components/QuickActions.tsx','src/components/StatCard.tsx','src/components/RecentActivity.tsx','src/components/EmptyState.tsx',
  'src/components/ProjectDialog.tsx','src/components/BookDialog.tsx','src/components/BookCard.tsx',
  'src/components/WorkHeader.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx','src/components/IdentificationStage.tsx','src/components/ProjectStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/pages/VerbumApp.tsx','src/main.tsx',
  'src/styles/verbum.css','src/styles/library.css','src/styles/workspace.css','src/styles/identification.css','src/styles/project-stage.css','src/styles/technical.css',
  'src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-runtime.js','src/project-stage-runtime.js','src/technical-runtime.js','src/vite-env.d.ts',
];
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
for (const expected of ['getHealth()','getCurrentUser()','getLibrary()','getWorkWorkspace','verbum_work','verbum_stage','<WorkWorkspace','<WorkHeader','identification.css','project-stage.css']) {
  if (!app.includes(expected)) throw new Error(`VerbumApp missing workspace requirement: ${expected}`);
}

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
for (const expected of ['IdentificationStage','ProjectStage','WorkWorkflow','WorkspaceFooter','beforeunload','onWorkspaceChange','onPersisted']) {
  if (!workspace.includes(expected)) throw new Error(`WorkWorkspace missing Sprint 06 integration: ${expected}`);
}

const identification = await readFile(resolve(root, 'src/components/IdentificationStage.tsx'), 'utf8');
for (const expected of ['Título','Subtítulo','Status','Gênero','Idioma','Público-alvo','Sinopse','Palavras-chave','Capa da obra','Cor da obra','Progresso da Etapa']) {
  if (!identification.includes(expected)) throw new Error(`IdentificationStage missing: ${expected}`);
}

const projectStage = await readFile(resolve(root, 'src/components/ProjectStage.tsx'), 'utf8');
for (const expected of [
  'Projeto da Obra','Propósito da Obra','Objetivo Geral','Objetivos Específicos','Adicionar objetivo','Finalidade da Obra','Público e Impacto','Público-Alvo',
  'Benefícios Esperados para o Leitor','Transformação Esperada','Identidade da Obra','Mensagem Central','Diferenciais da Obra','Proposta de Valor','Palavra-chave da Obra',
  'Inspiração','Motivação Pessoal','Versículo Inspirador','Frase Norteadora','Progresso da Etapa','completeWorkProject','saveWorkProject','getWorkProject',
]) {
  if (!projectStage.includes(expected)) throw new Error(`ProjectStage missing: ${expected}`);
}

const bookDialog = await readFile(resolve(root, 'src/components/BookDialog.tsx'), 'utf8');
for (const expected of ['Projeto *','Título *','Subtítulo','identificação editorial completa']) {
  if (!bookDialog.includes(expected)) throw new Error(`Simplified BookDialog missing: ${expected}`);
}
for (const removed of ['Capítulos previstos','Meta de palavras','Coautor (opcional)','Série (opcional)']) {
  if (bookDialog.includes(removed)) throw new Error(`BookDialog must not duplicate Identification field: ${removed}`);
}

const bookCard = await readFile(resolve(root, 'src/components/BookCard.tsx'), 'utf8');
for (const expected of ['Situação:','stageLabels','Etapa atual']) {
  if (!bookCard.includes(expected)) throw new Error(`BookCard missing workflow clarification: ${expected}`);
}

const footer = await readFile(resolve(root, 'src/components/WorkspaceFooter.tsx'), 'utf8');
for (const expected of ['hidePrevious','is-first-stage','verbum-workspace-footer-spacer']) {
  if (!footer.includes(expected)) throw new Error(`WorkspaceFooter missing first-stage behavior: ${expected}`);
}

const libraryService = await readFile(resolve(root, 'src/services/library-service.ts'), 'utf8');
for (const expected of ['/workspace','/identification','/project-stage','/project-stage/complete','getWorkProject','saveWorkProject','completeWorkProject']) {
  if (!libraryService.includes(expected)) throw new Error(`Library service missing endpoint: ${expected}`);
}

const projectCss = await readFile(resolve(root, 'src/styles/project-stage.css'), 'utf8');
for (const expected of ['.verbum-project-stage-layout','.verbum-project-card','.verbum-project-progress','.verbum-project-checklist','.verbum-objective-row','@media']) {
  if (!projectCss.includes(expected)) throw new Error(`Projeto da Obra CSS missing: ${expected}`);
}

const technicalCss = await readFile(resolve(root, 'src/styles/technical.css'), 'utf8');
for (const expected of ['[data-verbum-app]','position:fixed','body.admin-bar','is-first-stage','.verbum-form-helper']) {
  if (!technicalCss.includes(expected)) throw new Error(`Technical CSS missing: ${expected}`);
}

const projectRuntime = await readFile(resolve(root, 'src/project-stage-runtime.js'), 'utf8');
for (const expected of ['/project-stage','/project-stage/complete','MutationObserver','Progresso da Etapa','Adicionar objetivo','beforeunload']) {
  if (!projectRuntime.includes(expected)) throw new Error(`Projeto da Obra runtime missing: ${expected}`);
}

const technicalRuntime = await readFile(resolve(root, 'src/technical-runtime.js'), 'utf8');
for (const expected of ['data-book-form','Situação:','hideIdentificationPrevious','ÚLTIMA EDIÇÃO','MutationObserver']) {
  if (!technicalRuntime.includes(expected)) throw new Error(`Technical runtime missing: ${expected}`);
}

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
for (const expected of ['static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js','project-stage-runtime.js','technical-runtime.js','source+query']) {
  if (!buildJs.includes(expected)) throw new Error(`Static JS build must load: ${expected}`);
}
for (const expected of ['verbum.css','library.css','workspace.css','identification.css','project-stage.css','technical.css']) {
  if (!buildCss.includes(expected)) throw new Error(`Static CSS build missing: ${expected}`);
}

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}
console.log('Frontend technical hardening checks passed');
