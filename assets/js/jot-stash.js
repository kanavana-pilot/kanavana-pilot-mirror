(function(){
  function qa(sel,root){return Array.from((root||document).querySelectorAll(sel))}
  qa('form[data-confirm]').forEach(function(f){
    f.addEventListener('submit', function(ev){
      var msg = f.getAttribute('data-confirm') || 'Are you sure?';
      if(!confirm(msg)) ev.preventDefault();
    });
  });
})();
