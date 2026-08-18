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
  var loadedScripts={};
  var loadedStyles={};
  var core=['auth-profile-runtime.js','static-runtime.js'];
  var enhancements=['workspace-mobile-runtime.js','workspace-ui-runtime.js','workspace-manager-runtime.js','hom023a-journey-runtime.js','hom027-journey-runtime.js','workspace-book-dialog-runtime.js','project-stage-runtime.js','technical-runtime.js','dashboard-official-runtime.js','sidebar-profile-runtime.js','minhas-obras-runtime.js','profile-polish-runtime.js'];
  var stageScripts={
    identification:['identification-initial-prelude.js','identification-runtime.js'],
    project:['project-stage-runtime.js','foundation-intention-runtime.js','foundation-reader-result-runtime.js','foundation-truth-central-runtime.js'],
    planning:['planning-stage-runtime.js','structure-direction-runtime.js','structure-architecture-runtime.js','structure-elements-runtime.js'],
    development:['development-stage-runtime.js','chapter-workflow-runtime.js','chapter-preparation-runtime.js','chapter-research-runtime.js','chapter-writing-runtime.js','writing-hom010-hotfix.js','writing-hom011-hotfix.js','revision-hom012-hotfix.js','chapter-revision-runtime.js','revision-hom013-hotfix.js','navigation-hom014-hotfix.js','editorial-editor-hom016.js','editorial-editor-hom016b.js','editorial-menu-hom016c.js','editorial-writing-hom016d.js','editorial-writing-hom016e.js'],
    general_review:['general-review-runtime.js'],
    versions:['work-versions-runtime.js'],
    audit:['work-audit-runtime.js'],
    editorial_desk:['editorial-desk-runtime.js'],
    layout:['layout-stage-runtime.js'],
    legal:['legal-stage-runtime.js'],
    publication:['publication-stage-runtime.js']
  };
  var stageStyles={
    identification:['identification.css','identification-hom027-polish.css'],
    project:['project-stage.css'],
    planning:['planning-stage.css'],
    development:['development-stage.css','chapter-workflow.css','chapter-preparation.css','chapter-research.css','chapter-writing.css','chapter-revision.css','revision-hom013.css','navigation-hom014.css','editorial-editor-hom016.css','editorial-editor-hom016a.css','editorial-editor-hom016b.css','editorial-writing-hom016d.css','editorial-writing-hom016e.css'],
    general_review:['general-review.css'],
    versions:['work-versions.css'],
    audit:['work-audit.css'],
    editorial_desk:['editorial-desk.css'],
    layout:['layout-stage.css'],
    legal:['legal-stage.css'],
    publication:['publication-stage.css']
  };
  function source(file){return current.src.replace(/build\\/verbum-app\\.js(?:\\?.*)?$/,'frontend/app/src/'+file);}
  function styleSource(file){return current.src.replace(/build\\/verbum-app\\.js(?:\\?.*)?$/,'frontend/app/src/styles/'+file);}
  function loadScript(file){
    if(loadedScripts[file]){return loadedScripts[file];}
    loadedScripts[file]=new Promise(function(resolve){
      var existing=document.querySelector('script[data-verbum-runtime="'+file+'"]');
      if(existing){resolve();return;}
      var url=source(file);if(!url||url===current.src){resolve();return;}
      var script=document.createElement('script');script.async=false;script.dataset.verbumRuntime=file;script.src=url+query;script.onload=resolve;script.onerror=resolve;document.head.appendChild(script);
    });
    return loadedScripts[file];
  }
  function loadStyle(file){
    if(loadedStyles[file]){return;}
    loadedStyles[file]=true;
    if(document.querySelector('link[data-verbum-style="'+file+'"]')){return;}
    var link=document.createElement('link');link.rel='stylesheet';link.dataset.verbumStyle=file;link.href=styleSource(file)+query;document.head.appendChild(link);
  }
  function route(){
    var params=new URLSearchParams(location.search);
    return {book:params.get('verbum_work'),stage:params.get('verbum_stage')||''};
  }
  function ensureRouteAssets(){
    var r=route();if(!r.book){return;}
    loadStyle('workspace.css');
    (stageStyles[r.stage]||[]).forEach(loadStyle);
    (stageScripts[r.stage]||[]).forEach(loadScript);
  }
  function notify(){window.dispatchEvent(new Event('verbum:routechange'));}
  var push=history.pushState,replace=history.replaceState;
  history.pushState=function(){var result=push.apply(this,arguments);notify();return result;};
  history.replaceState=function(){var result=replace.apply(this,arguments);notify();return result;};
  window.addEventListener('popstate',ensureRouteAssets);
  window.addEventListener('verbum:routechange',ensureRouteAssets);
  loadScript(core[0]).then(function(){return loadScript(core[1]);}).then(function(){
    loadStyle('workspace-manager.css');
    ensureRouteAssets();
    enhancements.forEach(loadScript);
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
@import url("../frontend/app/src/styles/revision-hom013.css");
@import url("../frontend/app/src/styles/navigation-hom014.css");
@import url("../frontend/app/src/styles/editorial-editor-hom016.css");
@import url("../frontend/app/src/styles/editorial-editor-hom016a.css");
@import url("../frontend/app/src/styles/editorial-editor-hom016b.css");
@import url("../frontend/app/src/styles/editorial-writing-hom016d.css");
@import url("../frontend/app/src/styles/editorial-writing-hom016e.css");
@import url("../frontend/app/src/styles/general-review.css");
@import url("../frontend/app/src/styles/work-versions.css");
@import url("../frontend/app/src/styles/work-audit.css");
@import url("../frontend/app/src/styles/editorial-desk.css");
@import url("../frontend/app/src/styles/layout-stage.css");
@import url("../frontend/app/src/styles/legal-stage.css");
@import url("../frontend/app/src/styles/publication-stage.css");
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
console.log('Built Verbum Studio assets with lazy stage loading');
