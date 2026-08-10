(function(){
  var current=document.currentScript;
  if(!current||!current.src){return;}
  var query='';
  try{query=new URL(current.src,document.baseURI).search||'';}catch(e){query='';}
  ['static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js','project-stage-runtime.js','technical-runtime.js','dashboard-official-runtime.js'].forEach(function(file){
    var source=current.src.replace(/build\/verbum-app\.js(?:\?.*)?$/,'frontend/app/src/'+file);
    if(!source||source===current.src){return;}
    var script=document.createElement('script');
    script.src=source+query;
    script.defer=false;
    document.head.appendChild(script);
  });
})();
