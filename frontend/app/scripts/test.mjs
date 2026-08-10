import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts','src/services/core-service.ts','src/services/library-service.ts',
  'src/components/AppShell.tsx','src/components/Sidebar.tsx','src/components/Header.tsx','src/components/UserMenu.tsx',
  'src/components/ProjectDialog.tsx','src/components/BookDialog.tsx','src/components/BookCard.tsx',
  'src/components/WorkHeader.tsx','src/components/WorkWorkflow.tsx','src/components/WorkspaceFooter.tsx','src/components/IdentificationStage.tsx','src/components/ProjectStage.tsx',
  'src/pages/Dashboard.tsx','src/pages/LibraryPage.tsx','src/pages/WorkWorkspace.tsx','src/pages/VerbumApp.tsx','src/main.tsx',
  'src/styles/verbum.css','src/styles/library.css','src/styles/workspace.css','src/styles/identification.css','src/styles/project-stage.css','src/styles/technical.css','src/styles/dashboard-official.css','src/styles/minhas-obras.css','src/styles/auth-profile.css',
  'src/auth-profile-runtime.js','src/static-runtime.js','src/workspace-mobile-runtime.js','src/identification-runtime.js','src/project-stage-runtime.js','src/technical-runtime.js','src/dashboard-official-runtime.js','src/sidebar-profile-runtime.js','src/minhas-obras-runtime.js','src/vite-env.d.ts',
];

for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

function requireAll(contents, expected, label) {
  for (const item of expected) if (!contents.includes(item)) throw new Error(`${label} missing: ${item}`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
requireAll(app, ['getHealth()','getCurrentUser()','getLibrary()','getWorkWorkspace','<WorkWorkspace','<WorkHeader','hideHeader'], 'VerbumApp');

const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8');
if (!sidebar.includes('Minhas Obras')) throw new Error('Sidebar must use Minhas Obras');

const dashboard = await readFile(resolve(root, 'src/pages/Dashboard.tsx'), 'utf8');
requireAll(dashboard, ['VERBUM STUDIO','Estatísticas','Últimas Obras','Próxima Ação','Índice de Maturidade da Obra','Radar de Maturidade','Progresso Geral','Acesso Rápido','Tendência'], 'Dashboard');

const workspace = await readFile(resolve(root, 'src/pages/WorkWorkspace.tsx'), 'utf8');
requireAll(workspace, ['IdentificationStage','ProjectStage','WorkWorkflow','WorkspaceFooter','beforeunload'], 'WorkWorkspace');

const authRuntime = await readFile(resolve(root, 'src/auth-profile-runtime.js'), 'utf8');
requireAll(authRuntime, ['/auth/login','/auth/register','/auth/forgot-password','/auth/reset-password','/auth/verify-email','/profile','/profile/avatar','data-verbum-auth-root','data-verbum-profile','accepted_terms','Minha conta','Trocar foto'], 'Auth/profile runtime');

const authCss = await readFile(resolve(root, 'src/styles/auth-profile.css'), 'utf8');
requireAll(authCss, ['.verbum-auth-shell','.verbum-auth-card','.verbum-profile-dialog','.verbum-profile-avatar','.verbum-sidebar-profile-main','@media'], 'Auth/profile CSS');

const sidebarRuntime = await readFile(resolve(root, 'src/sidebar-profile-runtime.js'), 'utf8');
requireAll(sidebarRuntime, ['data-verbum-profile','avatarUrl','VerbumAuthProfile.logout','data-sidebar-collapse'], 'Sidebar profile runtime');

const minhasRuntime = await readFile(resolve(root, 'src/minhas-obras-runtime.js'), 'utf8');
requireAll(minhasRuntime, ['Minhas Obras','Pesquisar obras','data-minhas-stage','Ainda não calculado','Abrir Obra','MutationObserver','setTextIfChanged',"=== '‹ Obras'"], 'Minhas Obras runtime');
if (minhasRuntime.includes("if (shortcut) shortcut.textContent = 'Minhas Obras';")) throw new Error('Minhas Obras runtime must keep MutationObserver idempotent');

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
requireAll(buildJs, ['auth-profile-runtime.js','static-runtime.js','sidebar-profile-runtime.js','minhas-obras-runtime.js','script.async=false'], 'Static JS build');
if (buildJs.indexOf('auth-profile-runtime.js') > buildJs.indexOf('static-runtime.js')) throw new Error('Auth runtime must load before static runtime');

const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
requireAll(buildCss, ['verbum.css','dashboard-official.css','minhas-obras.css','auth-profile.css'], 'Static CSS build');

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}

console.log('Frontend Dashboard + Minhas Obras + autenticação e perfil checks passed');
