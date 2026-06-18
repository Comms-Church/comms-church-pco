/* Comms.Church PCO — front-end JS */
(function () {
  'use strict';

  // ---- Add-to-Calendar dropdown ------------------------------------------
  document.addEventListener('click', function (e) {
    var trigger  = e.target.closest('.ccpco-cal-trigger');
    var inDrop   = e.target.closest('.ccpco-cal-dropdown');

    if (!trigger && !inDrop) {
      document.querySelectorAll('.ccpco-cal-wrap.open').forEach(function (w) {
        w.classList.remove('open');
        var t = w.querySelector('.ccpco-cal-trigger');
        if (t) t.setAttribute('aria-expanded', 'false');
      });
      return;
    }

    if (trigger) {
      e.stopPropagation();
      var wrap   = trigger.closest('.ccpco-cal-wrap');
      var isOpen = wrap.classList.contains('open');
      document.querySelectorAll('.ccpco-cal-wrap.open').forEach(function (w) {
        if (w !== wrap) {
          w.classList.remove('open');
          var t = w.querySelector('.ccpco-cal-trigger');
          if (t) t.setAttribute('aria-expanded', 'false');
        }
      });
      wrap.classList.toggle('open', !isOpen);
      trigger.setAttribute('aria-expanded', String(!isOpen));
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.ccpco-cal-wrap.open').forEach(function (w) {
        w.classList.remove('open');
        var t = w.querySelector('.ccpco-cal-trigger');
        if (t) t.setAttribute('aria-expanded', 'false');
      });
    }
  });

})();
