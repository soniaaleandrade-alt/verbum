import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(new URL('../', import.meta.url).pathname);
const files = [
  'src/services/api-client.ts',
  'src/services/core-service.ts',
  'src/pages/DiagnosticPage.tsx',
];

for (const file of files) {
  const contents = await readFile(resolve(root, file), 'utf8');
  if (!contents.trim()) {
    throw new Error(`${file} is empty`);
  }
}

const page = await readFile(resolve(root, 'src/pages/DiagnosticPage.tsx'), 'utf8');
for (const expected of ['Carregando Verbum Studio', 'VERBUM STUDIO', 'Sistema inicializado', 'Não foi possível carregar']) {
  if (!page.includes(expected)) {
    throw new Error(`DiagnosticPage missing text: ${expected}`);
  }
}

const apiClient = await readFile(resolve(root, 'src/services/api-client.ts'), 'utf8');
if (!apiClient.includes('fetch(') || !apiClient.includes('X-WP-Nonce')) {
  throw new Error('API client must centralize fetch with the WordPress REST nonce');
}

console.log('Frontend static checks passed');
