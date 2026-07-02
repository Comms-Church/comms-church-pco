/* Comms.Church PCO — front-end JS */
(function () {
  'use strict';

  function closeAll(except) {
    document.querySelectorAll('.ccpco-cal-wrap.open').forEach(function (w) {
      if (w === except) return;
      w.classList.remove('open', 'ccpco-drop-down');
      var t = w.querySelector('.ccpco-cal-trigger');
      if (t) t.setAttribute('aria-expanded', 'false');
    });
  }

  // ---- Add-to-Calendar dropdown ------------------------------------------
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.ccpco-cal-trigger');
    var inDrop  = e.target.closest('.ccpco-cal-dropdown');

    if (!trigger && !inDrop) { closeAll(null); return; }

    if (trigger) {
      e.stopPropagation();
      var wrap   = trigger.closest('.ccpco-cal-wrap');
      var isOpen = wrap.classList.contains('open');
      closeAll(wrap);

      if (isOpen) {
        wrap.classList.remove('open', 'ccpco-drop-down');
        trigger.setAttribute('aria-expanded', 'false');
        return;
      }

      // Smart flip: dropdown opens upward by default; if there isn't
      // ~220px of room above the trigger, open downward instead.
      var rect = trigger.getBoundingClientRect();
      wrap.classList.toggle('ccpco-drop-down', rect.top < 230);

      wrap.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll(null);
  });

})();
