// Minimal UI kit: dark mode toggle, toasts, skeleton helpers
(function(){
  // Ensure early theme init sync
  try {
    var theme = localStorage.getItem('theme');
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var el = document.documentElement;
    el.classList.remove('dark');
    if (theme === 'dark' || (!theme && prefersDark)) {
      el.classList.add('dark');
    }
  } catch (e) {}
  // Dark mode toggle
  var toggle = document.getElementById('darkModeToggle');
  if (toggle) {
    toggle.addEventListener('click', function(){
      var html = document.documentElement;
      var isDark = html.classList.toggle('dark');
      try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
      this.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    });
    // Init icon state
    var isDark = document.documentElement.classList.contains('dark');
    toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    toggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
  }

  // Toasts API
  var root = document.getElementById('toast-root');
  function makeToast(options){
    if (!root) return;
    var type = options.type || 'info';
    var title = options.title || '';
    var message = options.message || '';
    var duration = options.duration || 3000;

    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML =
      '<div class="mt-0.5">' +
        (title ? '<div class="font-semibold mb-0.5">'+title+'</div>' : '') +
        '<div class="text-sm">'+message+'</div>' +
      '</div>' +
      '<button class="ml-auto text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus-ring" aria-label="Dismiss">' +
        '<i class="fas fa-times"></i>' +
      '</button>';

    var remove = function(){
      if (!el.parentNode) return;
      el.style.transition = 'opacity .2s ease, transform .2s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(function(){ if (el.parentNode) root.removeChild(el); }, 200);
    };

    el.querySelector('button').addEventListener('click', remove);
    root.appendChild(el);
    requestAnimationFrame(function(){
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    });
    if (duration > 0) setTimeout(remove, duration);
  }

  window.UIKit = {
    toast: makeToast,
    success: function(msg, title){ makeToast({ type: 'success', message: msg, title: title }); },
    error: function(msg, title){ makeToast({ type: 'error', message: msg, title: title }); },
    info: function(msg, title){ makeToast({ type: 'info', message: msg, title: title }); },
    warning: function(msg, title){ makeToast({ type: 'warning', message: msg, title: title }); },
  };
})();


