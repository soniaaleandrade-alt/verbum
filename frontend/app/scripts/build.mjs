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
  var query='';
  try{query=new URL(current.src,document.baseURI).search||'';}catch(e){query='';}
  ['auth-profile-runtime.js','static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js','project-stage-runtime.js','planning-stage-runtime.js','development-stage-runtime.js','chapter-workflow-runtime.js','chapter-preparation-runtime.js','chapter-research-runtime.js','chapter-writing-runtime.js','chapter-revision-runtime.js','general-review-runtime.js','work-versions-runtime.js','technical-runtime.js','dashboard-official-runtime.js','sidebar-profile-runtime.js','minhas-obras-runtime.js','profile-polish-runtime.js'].forEach(function(file){
    var source=current.src.replace(/build\\/verbum-app\\.js(?:\\?.*)?$/,'frontend/app/src/'+file);
    if(!source||source===current.src){return;}
    var script=document.createElement('script');
    script.async=false;
    script.src=source+query;
    document.head.appendChild(script);
  });
})();
`;

const css = `@import url("../frontend/app/src/styles/verbum.css");
@import url("../frontend/app/src/styles/library.css");
@import url("../frontend/app/src/styles/workspace.css");
@import url("../frontend/app/src/styles/identification.css");
@import url("../frontend/app/src/styles/project-stage.css");
@import url("../frontend/app/src/styles/planning-stage.css");
@import url("../frontend/app/src/styles/development-stage.css");
@import url("../frontend/app/src/styles/chapter-workflow.css");
@import url("../frontend/app/src/styles/chapter-preparation.css");
@import url("../frontend/app/src/styles/chapter-research.css");
@import url("../frontend/app/src/styles/chapter-writing.css");
@import url("../frontend/app/src/styles/chapter-revision.css");
@import url("../frontend/app/src/styles/general-review.css");
@import url("../frontend/app/src/styles/work-versions.css");
@import url("../frontend/app/src/styles/technical.css");
@import url("../frontend/app/src/styles/dashboard-official.css");
@import url("../frontend/app/src/styles/dashboard-polish.css");
@import url("../frontend/app/src/styles/sidebar-profile.css");
@import url("../frontend/app/src/styles/minhas-obras.css");
@import url("../frontend/app/src/styles/auth-profile.css");
@import url("../frontend/app/src/styles/profile-polish.css");
`;

await mkdir(buildDir, { recursive: true });
await writeFile(resolve(buildDir, 'verbum-app.js'), js);
await writeFile(resolve(buildDir, 'verbum-app.css'), css);
console.log('Built Verbum Studio assets through Sprint 14 Controle de Versões');
