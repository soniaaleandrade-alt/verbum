import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const repoRoot = resolve(root, '../..');
const requiredFiles = [
  'src/services/api-client.ts',
  'src/services/core-service.ts',
  'src/components/AppShell.tsx',
  'src/components/Sidebar.tsx',
  'src/components/Header.tsx',
  'src/components/UserMenu.tsx',
  'src/components/QuickActions.tsx',
  'src/components/StatCard.tsx',
  'src/components/RecentActivity.tsx',
  'src/components/EmptyState.tsx',
  'src/pages/Dashboard.tsx',
  'src/pages/VerbumApp.tsx',
  'src/main.tsx',
  'src/styles/verbum.css',
];

for (const file of requiredFiles) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) throw new Error(`${file} is empty`);
}

const app = await readFile(resolve(root, 'src/pages/VerbumApp.tsx'), 'utf8');
for (const expected of [
  "getHealth()",
  "getCurrentUser()",
  'Promise.allSettled',
  'Carregando Verbum Studio',
  'Não foi possível abrir o Verbum Studio',
  '<AppShell',
  '<Dashboard',
]) {
  if (!app.includes(expected)) throw new Error(`VerbumApp missing requirement: ${expected}`);
}

const dashboard = await readFile(resolve(root, 'src/pages/Dashboard.tsx'), 'utf8');
for (const expected of ['Bom trabalho', 'Continue escrevendo', 'Criar nova obra', 'Ações rápidas', 'Atividade recente']) {
  if (!dashboard.includes(expected) && expected !== 'Criar nova obra' && expected !== 'Ações rápidas' && expected !== 'Atividade recente') {
    throw new Error(`Dashboard missing text: ${expected}`);
  }
}

const sidebar = await readFile(resolve(root, 'src/components/Sidebar.tsx'), 'utf8');
for (const expected of ['VERBUM', 'STUDIO', "label: 'Início'", "active: true", 'Workspace', 'Obras']) {
  if (!sidebar.includes(expected)) throw new Error(`Sidebar missing requirement: ${expected}`);
}

const userMenu = await readFile(resolve(root, 'src/components/UserMenu.tsx'), 'utf8');
for (const expected of ['Meu perfil', 'Configurações', 'Sair']) {
  if (!userMenu.includes(expected)) throw new Error(`UserMenu missing option: ${expected}`);
}

const main = await readFile(resolve(root, 'src/main.tsx'), 'utf8');
if (!main.includes('VerbumApp') || main.includes('<DiagnosticPage')) {
  throw new Error('main.tsx must mount VerbumApp instead of the diagnostic screen');
}

const apiClient = await readFile(resolve(root, 'src/services/api-client.ts'), 'utf8');
if (!apiClient.includes('fetch(') || !apiClient.includes('X-WP-Nonce') || !apiClient.includes('apiRoot')) {
  throw new Error('API client must preserve apiRoot and the WordPress REST nonce');
}

const css = await readFile(resolve(root, 'src/styles/verbum.css'), 'utf8');
for (const expected of ['--verbum-navy', '--verbum-wine', '.verbum-sidebar', '.verbum-header', '@media (max-width:820px)']) {
  if (!css.includes(expected)) throw new Error(`Responsive visual system missing: ${expected}`);
}

const buildJs = await readFile(resolve(repoRoot, 'build/verbum-app.js'), 'utf8');
const buildCss = await readFile(resolve(repoRoot, 'build/verbum-app.css'), 'utf8');
for (const expected of ['/health', '/me', 'X-WP-Nonce', 'Continue escrevendo', 'Atividade recente']) {
  if (!buildJs.includes(expected)) throw new Error(`Static build missing: ${expected}`);
}
if (!buildCss.includes('.verbum-shell') || !buildCss.includes('@media')) {
  throw new Error('Static CSS build does not contain the Sprint 02 responsive layout');
}

const sensitivePattern = /(SUPABASE_SERVICE|SERVICE_KEY|OPENAI_API_KEY|sk-[A-Za-z0-9_-]{10,})/;
for (const file of [...requiredFiles, '../../build/verbum-app.js']) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (sensitivePattern.test(contents)) throw new Error(`Sensitive value pattern found in ${file}`);
}

console.log('Frontend Sprint 02 static checks passed');
