/* Comms.Church PCO — front-end JS */
(function () {
  'use strict';

  function options(wrap) {
    return Array.prototype.slice.call(wrap.querySelectorAll('.ccpco-cal-option'));
  }

  function close(wrap, returnFocus) {
    wrap.classList.remove('open', 'ccpco-drop-up');
    var t = wrap.querySelector('.ccpco-cal-trigger');
    if (t) {
      t.setAttribute('aria-expanded', 'false');
      if (returnFocus) t.focus();
    }
  }

  function closeAll(except) {
    document.querySelectorAll('.ccpco-cal-wrap.open').forEach(function (w) {
      if (w !== except) close(w, false);
    });
  }

  function open(wrap, trigger) {
    closeAll(wrap);
    // Menus drop down; flip up only when there isn't ~220px of room below.
    var rect = trigger.getBoundingClientRect();
    wrap.classList.toggle('ccpco-drop-up', window.innerHeight - rect.bottom < 230 && rect.top > 230);
    wrap.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
    var first = options(wrap)[0];
    if (first) first.focus();
  }

  // ---- Add-to-Calendar dropdown ------------------------------------------
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.ccpco-cal-trigger');
    var inDrop  = e.target.closest('.ccpco-cal-dropdown');

    if (!trigger && !inDrop) { closeAll(null); return; }

    if (trigger) {
      e.stopPropagation();
      var wrap = trigger.closest('.ccpco-cal-wrap');
      if (wrap.classList.contains('open')) close(wrap, false);
      else open(wrap, trigger);
    }
  });

  document.addEventListener('keydown', function (e) {
    var wrap = e.target.closest && e.target.closest('.ccpco-cal-wrap.open');

    if (e.key === 'Escape') {
      if (wrap) close(wrap, true);
      else closeAll(null);
      return;
    }
    if (!wrap || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;

    // Arrow keys move through the menu items, wrapping at the ends.
    var items = options(wrap);
    var i = items.indexOf(document.activeElement);
    var next = e.key === 'ArrowDown' ? i + 1 : i - 1;
    if (next < 0) next = items.length - 1;
    if (next >= items.length) next = 0;
    e.preventDefault();
    items[next].focus();
  });

  // Tabbing out of an open menu closes it instead of leaving it over the page.
  document.addEventListener('focusout', function (e) {
    var wrap = e.target.closest && e.target.closest('.ccpco-cal-wrap.open');
    if (wrap && e.relatedTarget && !wrap.contains(e.relatedTarget)) close(wrap, false);
  });

})();
