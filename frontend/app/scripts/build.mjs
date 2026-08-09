import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(here, '..');
const repoRoot = resolve(appRoot, '../..');
const buildDir = resolve(repoRoot, 'build');

const js = `(function(){
  var current=document.currentScript;
  if(!current||!current.src){return;}
  ['static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js','project-stage-runtime.js'].forEach(function(file){
    var source=current.src.replace(/build\\/verbum-app\\.js(?:\\?.*)?$/,'frontend/app/src/'+file);
    if(!source||source===current.src){return;}
    var script=document.createElement('script');
    script.src=source;
    script.defer=false;
    document.head.appendChild(script);
  });
})();
`;

const css = `@import url("../frontend/app/src/styles/verbum.css");
@import url("../frontend/app/src/styles/library.css");
@import url("../frontend/app/src/styles/workspace.css");
@import url("../frontend/app/src/styles/identification.css");
@import url("../frontend/app/src/styles/project-stage.css");
`;

await mkdir(buildDir, { recursive: true });
await writeFile(resolve(buildDir, 'verbum-app.js'), js);
await writeFile(resolve(buildDir, 'verbum-app.css'), css);
console.log('Built Sprint 06 build/verbum-app.js and build/verbum-app.css');
