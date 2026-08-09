import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts',
  'src/services/core-service.ts',
  'src/services/library-service.ts',
  'src/components/AppShell.tsx',
  'src/components/Sidebar.tsx',
  'src/components/Header.tsx',
  'src/components/UserMenu.tsx',
  'src/components/QuickActions.tsx',
  'src/components/StatCard.tsx',
  'src/components/RecentActivity.tsx',
  'src/components/EmptyState.tsx',
  'src/components/ProjectDialog.tsx',
  'src/components/BookDialog.tsx',
  'src/components/BookCard.tsx',
  'src/pages/Dashboard.tsx',
  'src/pages/LibraryPage.tsx',
  'src/pages/VerbumApp.tsx',
  'src/main.tsx',
  'src/styles/verbum.css',
  'src/styles/library.css',
  'src/static-runtime.js',
];

for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
for (const expected of [
  'getHealth()',
  'getCurrentUser()',
  'getLibrary()',
  'Promise.allSettled',
  "activeSection === 'dashboard'",
  '<LibraryPage',
  'createProjectAndRefresh',
  'createBookAndRefresh',
  'Carregando Verbum Studio',
]) {
  if (!app.includes(expected)) throw new Error(`VerbumApp missing requirement: ${expected}`);
}

const libraryPage = await readFile(resolve(root, 'src/pages/LibraryPage.tsx'), 'utf8');
for (const expected of ['Banco de Obras', 'Novo projeto', 'Criar nova obra', 'Arquivados', 'onArchiveBook', 'onUpdateProject']) {
  if (!libraryPage.includes(expected)) throw new Error(`LibraryPage missing requirement: ${expected}`);
}

const bookDialog = await readFile(resolve(root, 'src/components/BookDialog.tsx'), 'utf8');
for (const expected of ['Identificação da Obra', 'Título da obra', 'Subtítulo', 'Série (opcional)', 'Público-alvo', 'Idioma', 'Autor', 'Capítulos previstos', 'Meta de palavras']) {
  if (!bookDialog.includes(expected)) throw new Error(`Book identification missing: ${expected}`);
}

const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8');
for (const expected of ["section: 'dashboard'", "section: 'library'", "label: 'Início'", "label: 'Obras'", 'onNavigate']) {
  if (!sidebar.includes(expected)) throw new Error(`Sidebar missing Sprint 03 navigation: ${expected}`);
}

const dashboard = await readFile(resolve(root, 'src/pages/Dashboard.tsx'), 'utf8');
for (const expected of ['Obras em andamento', 'Capítulos previstos', 'Meta de palavras', 'Identificação da Obra', 'library.books']) {
  if (!dashboard.includes(expected)) throw new Error(`Dashboard missing live library indicator: ${expected}`);
}

const apiClient = await readFile(resolve(root, 'src/services/api-client.ts'), 'utf8');
for (const expected of ['fetch(', 'X-WP-Nonce', 'apiRoot', 'apiPost', 'apiPatch', 'Content-Type']) {
  if (!apiClient.includes(expected)) throw new Error(`API client missing write support: ${expected}`);
}

const libraryService = await readFile(resolve(root, 'src/services/library-service.ts'), 'utf8');
for (const expected of ['/library', '/projects', '/books', 'archiveProject', 'archiveBook']) {
  if (!libraryService.includes(expected)) throw new Error(`Library service missing endpoint: ${expected}`);
}

const css = await readFile(resolve(root, 'src/styles/verbum.css'), 'utf8');
const libraryCss = await readFile(resolve(root, 'src/styles/library.css'), 'utf8');
for (const expected of ['--verbum-navy', '--verbum-wine', '.verbum-sidebar', '.verbum-header', '@media (max-width:820px)']) {
  if (!css.includes(expected)) throw new Error(`Base visual system missing: ${expected}`);
}
for (const expected of ['.verbum-library', '.verbum-book-card', '.verbum-dialog', '.verbum-form-grid', '@media']) {
  if (!libraryCss.includes(expected)) throw new Error(`Banco de Obras styles missing: ${expected}`);
}

const runtime = await readFile(resolve(root, 'src/static-runtime.js'), 'utf8');
for (const expected of ['/health', '/me', '/library', '/projects', '/books', 'X-WP-Nonce', 'Banco de Obras', 'Identificação da Obra']) {
  if (!runtime.includes(expected)) throw new Error(`Static runtime missing Sprint 03 requirement: ${expected}`);
}

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
if (!buildJs.includes('frontend/app/src/static-runtime.js')) {
  throw new Error('Static JS build must load the Sprint 03 runtime');
}
if (!buildCss.includes('verbum.css') || !buildCss.includes('library.css')) {
  throw new Error('Static CSS build must load base and Banco de Obras styles');
}

const userMenu = await readFile(resolve(root, 'src/components/UserMenu.tsx'), 'utf8');
for (const expected of ['Meu perfil', 'Configurações', 'Sair']) {
  if (!userMenu.includes(expected)) throw new Error(`UserMenu missing option: ${expected}`);
}

const main = await readFile(resolve(root, 'src/main.tsx'), 'utf8');
if (!main.includes('VerbumApp') || main.includes('<DiagnosticPage')) {
  throw new Error('main.tsx must mount VerbumApp instead of the diagnostic screen');
}

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}
for (const file of ['build/verbum-app.js', 'build/verbum-app.css']) {
  const contents = await readFile(resolve(repoRoot, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}

console.log('Frontend Sprint 03 Banco de Obras static checks passed');
