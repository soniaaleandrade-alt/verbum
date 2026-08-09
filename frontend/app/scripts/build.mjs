import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(here, '..');
const repoRoot = resolve(appRoot, '../..');
const buildDir = resolve(repoRoot, 'build');

const [js, baseCss, libraryCss] = await Promise.all([
  readFile(resolve(appRoot, 'src/static-runtime.js'), 'utf8'),
  readFile(resolve(appRoot, 'src/styles/verbum.css'), 'utf8'),
  readFile(resolve(appRoot, 'src/styles/library.css'), 'utf8'),
]);

await mkdir(buildDir, { recursive: true });
await writeFile(resolve(buildDir, 'verbum-app.js'), js.trim() + '\n');
await writeFile(resolve(buildDir, 'verbum-app.css'), `${baseCss.trim()}\n${libraryCss.trim()}\n`);
console.log('Built Sprint 03 build/verbum-app.js and build/verbum-app.css');
