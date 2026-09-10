/* Oyejo Gas - foundation scripts (vanilla JS, no dependencies). */
(function () {
  'use strict';

  // Mobile navigation toggle.
  var t = document.getElementById('navToggle');
  var l = document.getElementById('navLinks');
  if (t && l) {
    t.addEventListener('click', function () {
      var open = l.classList.toggle('open');
      t.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Auto-dismiss flash alerts.
  var alerts = document.querySelectorAll('.alert');
  for (var i = 0; i < alerts.length; i++) {
    (function (el) {
      setTimeout(function () { el.classList.add('fade'); }, 6000);
    })(alerts[i]);
  }

  // Connection indicator (offline fallback pages arrive in Phase 27).
  function net() {
    var b = document.getElementById('netBanner');
    if (!navigator.onLine && !b) {
      var d = document.createElement('div');
      d.id = 'netBanner';
      d.className = 'maint';
      d.textContent = 'You appear to be offline. Some features may not work.';
      document.body.insertBefore(d, document.body.firstChild);
    } else if (navigator.onLine && b) {
      b.parentNode.removeChild(b);
    }
  }
  window.addEventListener('online', net);
  window.addEventListener('offline', net);

  // CSRF-aware POST helper for later phases (wallet, spin, checkout, ...).
  window.Oyejo = {
    csrf: function () {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : '';
    },
    post: function (url, data) {
      data = data || {};
      data.csrf_token = window.Oyejo.csrf();
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(data)
      });
    }
  };

  console.info('Oyejo Gas foundation ready.');
})();
