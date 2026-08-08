(function(){
  function escapeHtml(value){return String(value).replace(/[&<>"']/g,function(s){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s];});}
  function config(){return window.VerbumStudioConfig||{apiRoot:'/wp-json/verbum/v1',nonce:'',version:'1.0.0'};}
  function request(path){var c=config();return fetch(c.apiRoot+path,{credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce||''}}).then(function(response){return response.json();}).then(function(payload){if(!payload.success){throw new Error((payload.error&&payload.error.message)||'Não foi possível comunicar com a API.');}return payload.data;});}
  function render(element,html){element.innerHTML=html;}
  function mount(element){render(element,'<section class="verbum-app verbum-loading">Carregando Verbum Studio...</section>');Promise.all([request('/health'),request('/me')]).then(function(values){var health=values[0];var user=values[1];render(element,'<section class="verbum-app verbum-core"><h1>VERBUM STUDIO</h1><p>Sistema inicializado.</p><dl><dt>Núcleo:</dt><dd>OK</dd><dt>API:</dt><dd>'+(health.status==='ok'?'OK':'ERRO')+'</dd><dt>Usuário:</dt><dd>'+escapeHtml(user.name)+'</dd><dt>Versão:</dt><dd>'+escapeHtml(health.version)+'</dd></dl></section>');}).catch(function(){render(element,'<section class="verbum-app verbum-status verbum-error">Não foi possível carregar o Verbum Studio. Verifique sua autenticação e tente novamente.</section>');});}
  document.querySelectorAll('[data-verbum-app]').forEach(mount);
})();
