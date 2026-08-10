import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx',
  'src/components/ProjectDialog.tsx','src/components/BookDialog.tsx','src/components/BookCard.tsx',
  'src/components/WorkHeader.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx','src/components/IdentificationStage.tsx','src/components/ProjectStage.tsx','src/components/PlanningStage.tsx','src/components/DevelopmentStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/main.tsx','src/pages/VerbumApp.tsx',
  'src/styles/verbum.css','src/styles/library.css','src/styles/workspace.css','src/styles/identification.css','src/styles/project-stage.css','src/styles/planning-stage.css','src/styles/development-stage.css','src/styles/technical.css','src/styles/dashboard-official.css','src/styles/minhas-obras.css','src/styles/auth-profile.css','src/styles/profile-polish.css',
  'src/auth-profile-runtime.js','src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-runtime.js','src/project-stage-runtime.js','src/planning-stage-runtime.js','src/development-stage-runtime.js','src/technical-runtime.js','src/dashboard-official-runtime.js','src/sidebar-profile-runtime.js','src/minhas-obras-runtime.js','src/profile-polish-runtime.js','src/vite-env.d.ts',
];
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (!contents.trim()) throw new Error(`${file} is empty`); }
function requireAll(contents, expected, label) { for (const item of expected) if (!contents.includes(item)) throw new Error(`${label} missing: ${item}`); }
const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8'); requireAll(app, ['getHealth()','getCurrentUser()','getLibrary()','getWorkWorkspace','<WorkWorkspace','<WorkHeader','hideHeader'], 'VerbumApp');
const header = await readFile(resolve(root, 'src/components/Header.tsx'), 'utf8'); if (header.includes('Área atual')) throw new Error('Header must not render Área atual');
const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8'); if (!sidebar.includes('Minhas Obras')) throw new Error('Sidebar must use Minhas Obras');
const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8'); requireAll(workspace, ['IdentificationStage','ProjectStage','PlanningStage','DevelopmentStage','WorkWorkflow','WorkspaceFooter','beforeunload'], 'WorkWorkspace');
const planning = await readFile(resolve(root, 'src/components/PlanningStage.tsx'), 'utf8'); requireAll(planning, ['Planejamento da Obra','Pergunta Central','Tese Principal','Estrutura da Obra','Organização Editorial','Geração dos Capítulos','generatePlanningChapters','development'], 'PlanningStage');
const planningRuntime = await readFile(resolve(root, 'src/planning-stage-runtime.js'), 'utf8'); requireAll(planningRuntime, ['/planning-stage','/planning-stage/generate-chapters','/planning-stage/complete','Pergunta Central','Tese Principal','Estrutura da Obra','Geração dos Capítulos','Visualizar capítulos'], 'Planning runtime');
const planningCss = await readFile(resolve(root, 'src/styles/planning-stage.css'), 'utf8'); requireAll(planningCss, ['.verbum-planning-stage','.verbum-planning-layout','.verbum-outline-item','.verbum-generation-counts','@media'], 'Planning CSS');
const development = await readFile(resolve(root, 'src/components/DevelopmentStage.tsx'), 'utf8'); requireAll(development, ['Desenvolvimento da Obra','Gestão e redação dos capítulos da obra.','Preparação','Pesquisa','Redação','Revisão','Abrir capítulo','getDevelopmentChapter','completeWorkDevelopment'], 'DevelopmentStage');
const developmentRuntime = await readFile(resolve(root, 'src/development-stage-runtime.js'), 'utf8'); requireAll(developmentRuntime, ['/development-stage','/chapters/','Desenvolvimento da Obra','Abrir capítulo','Capítulo anterior','Próximo capítulo','verbum_chapter'], 'Development runtime');
const developmentCss = await readFile(resolve(root, 'src/styles/development-stage.css'), 'utf8'); requireAll(developmentCss, ['.verbum-development-stage','.verbum-development-stats','.verbum-chapter-card','.verbum-chapter-workspace','.verbum-chapter-workflow','@media'], 'Development CSS');
const authRuntime = await readFile(resolve(root, 'src/auth-profile-runtime.js'), 'utf8'); requireAll(authRuntime, ['/auth/login','/auth/register','/profile','Minha conta','Trocar foto'], 'Auth/profile runtime');
const profilePolishCss = await readFile(resolve(root, 'src/styles/profile-polish.css'), 'utf8'); requireAll(profilePolishCss, ['.verbum-sidebar-profile-main','grid-template-areas:"avatar copy"','.verbum-sidebar-avatar','grid-area:avatar','.verbum-sidebar-profile-copy','grid-area:copy','.verbum-sidebar-avatar img','position:absolute','object-fit:cover'], 'Profile polish CSS');
const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8'); requireAll(buildJs, ['auth-profile-runtime.js','static-runtime.js','project-stage-runtime.js','planning-stage-runtime.js','development-stage-runtime.js','sidebar-profile-runtime.js','profile-polish-runtime.js','script.async=false'], 'Static JS build');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8'); requireAll(buildCss, ['verbum.css','project-stage.css','planning-stage.css','development-stage.css','dashboard-official.css','minhas-obras.css','auth-profile.css','profile-polish.css'], 'Static CSS build');
const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) { const contents = await readFile(resolve(root, file), 'utf8'); if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`); }
console.log('Frontend Desenvolvimento + Planejamento + Dashboard + Minhas Obras + autenticação checks passed');
