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

  // Service worker (offline shell + push). Scope-safe: served from root.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var base = (document.querySelector('link[rel="manifest"]') || {}).href || '';
      var root = base ? base.replace(/manifest\.webmanifest.*$/, '') : './';
      navigator.serviceWorker.register(root + 'service-worker.js').catch(function () {});
    });
  }

  // Install prompt (PW-03): reveal the footer button when offered.
  var deferredPrompt = null;
  var installWrap = document.getElementById('installWrap');
  var installBtn = document.getElementById('installApp');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (installWrap) {
      installWrap.hidden = false;
    }
  });
  if (installBtn) {
    installBtn.addEventListener('click', function () {
      if (!deferredPrompt) {
        return;
      }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
        if (installWrap) {
          installWrap.hidden = true;
        }
      });
    });
  }

  // Web Push subscribe helper (used by the notifications page).
  function b64ToBytes(s) {
    var pad = '='.repeat((4 - (s.length % 4)) % 4);
    var bin = window.atob(s.replace(/-/g, '+').replace(/_/g, '/') + pad);
    var out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) {
      out[i] = bin.charCodeAt(i);
    }
    return out;
  }
  window.OyejoPush = {
    supported: function () {
      return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    },
    subscribe: function (vapidKey, saveUrl) {
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(vapidKey) });
      }).then(function (sub) {
        var raw = sub.toJSON();
        return window.Oyejo.post(saveUrl, {
          action: 'save', endpoint: raw.endpoint, keys: raw.keys
        }).then(function (r) { return r.json(); });
      });
    },
    unsubscribeAll: function (removeUrl) {
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.getSubscription();
      }).then(function (sub) {
        var ep = sub ? sub.endpoint : '';
        var done = sub ? sub.unsubscribe() : Promise.resolve(true);
        return done.then(function () {
          return window.Oyejo.post(removeUrl, { action: 'remove', endpoint: ep });
        }).then(function (r) { return r.json(); });
      });
    }
  };

  console.info('Oyejo Gas foundation ready.');
})();
