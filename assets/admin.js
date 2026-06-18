/* Comms.Church PCO — admin JS */
(function () {
  'use strict';

  var activeTab = 'signups';

  var DEFAULTS = {
    signups:        { display:'tiles', columns:'3', limit:'9', filter:'unarchived', category:'', show_closed:'false', image_shape:'cinematic', corner_radius:'8', brand_color:'', show_date:'true', show_location:'true', show_price:'true', show_calendar:'true', show_desc:'true', button_label:'', capacity:'0' },
    signup:         { show_desc:'true', show_times:'true', show_location:'true', show_tickets:'true', show_calendar:'true', brand_color:'', button_label:'' },
    register_button:{ label:'', brand_color:'', class:'' },
    attendee_count: { label:'registered' },
    signup_times:   { filter:'future', format:'F j, Y g:i a' },
    ticket_types:   { public_only:'true', show_free:'true' },
  };

  // ---- Populate signup pickers -------------------------------------------
  function populatePickers() {
    var signups = (window.CCPCOAdmin && CCPCOAdmin.signups) ? CCPCOAdmin.signups : [];
    document.querySelectorAll('.ccpco-signup-picker').forEach(function (sel) {
      sel.innerHTML = '<option value="">— select a signup —</option>';
      signups.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name + ' (ID: ' + s.id + ')';
        sel.appendChild(opt);
      });
    });
  }

  // ---- Build shortcode ---------------------------------------------------
  function collectAttrs() {
    var panel    = document.getElementById('tab-' + activeTab);
    if (!panel) return {};
    var defaults = DEFAULTS[activeTab] || {};
    var attrs    = {};

    panel.querySelectorAll('[data-attr]').forEach(function (el) {
      var attr = el.dataset.attr;

      if (el.classList.contains('ccpco-id-manual')) {
        var picker = panel.querySelector('.ccpco-signup-picker[data-attr="' + attr + '"]');
        if (picker && picker.value) return;
      }
      if (el.classList.contains('ccpco-signup-picker')) {
        var manual = panel.querySelector('.ccpco-id-manual[data-attr="' + attr + '"]');
        if (manual && manual.value.trim()) return;
      }

      var value;
      if (el.type === 'checkbox') {
        value = el.checked ? 'true' : 'false';
      } else if (el.type === 'color') {
        return;
      } else {
        value = el.value.trim();
      }

      if (value === '' || value === undefined) return;
      if (String(defaults[attr]) === String(value)) return;
      if (attr in attrs) return;
      attrs[attr] = value;
    });

    return attrs;
  }

  function buildShortcode() {
    var tag   = 'pco_' + activeTab;
    var attrs = collectAttrs();
    var sc    = '[' + tag;
    Object.keys(attrs).forEach(function (k) {
      sc += ' ' + k + '="' + String(attrs[k]).replace(/"/g, '&quot;') + '"';
    });
    return sc + ']';
  }

  function updateOutput() {
    var el = document.getElementById('ccpco-gen-output');
    if (el) el.textContent = buildShortcode();
  }

  // ---- Color pairs -------------------------------------------------------
  function wireColors(panel) {
    panel.querySelectorAll('input[type="color"]').forEach(function (picker) {
      var row  = picker.closest('td');
      var text = row ? row.querySelector('.ccpco-color-text, input[type="text"]') : null;
      if (!text) return;
      picker.addEventListener('input', function () { text.value = picker.value; updateOutput(); });
      text.addEventListener('input', function () {
        var v = text.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(v)) picker.value = v;
        updateOutput();
      });
    });
  }

  // ---- Tiles toggle ------------------------------------------------------
  function wireTilesToggle(panel) {
    var sel = panel.querySelector('[data-attr="display"]');
    if (!sel) return;
    function toggle() {
      panel.querySelectorAll('.row-tiles-only').forEach(function (r) {
        r.style.display = sel.value === 'tiles' ? '' : 'none';
      });
    }
    sel.addEventListener('change', function () { toggle(); updateOutput(); });
    toggle();
  }

  // ---- Tabs --------------------------------------------------------------
  document.querySelectorAll('.ccpco-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.ccpco-tab').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.ccpco-tab-panel').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      activeTab = btn.dataset.tab;
      var panel = document.getElementById('tab-' + activeTab);
      if (panel) panel.classList.add('active');
      updateOutput();
      schedulePreview();
    });
  });

  // ---- Wire all panels ---------------------------------------------------
  document.querySelectorAll('.ccpco-tab-panel').forEach(function (panel) {
    wireColors(panel);
    wireTilesToggle(panel);

    panel.querySelectorAll('[data-attr]').forEach(function (el) {
      el.addEventListener('change', function () { updateOutput(); schedulePreview(); });
      el.addEventListener('input',  function () { updateOutput(); schedulePreview(); });
    });

    panel.querySelectorAll('.ccpco-signup-picker').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var manual = panel.querySelector('.ccpco-id-manual[data-attr="' + sel.dataset.attr + '"]');
        if (manual && sel.value) manual.value = '';
        updateOutput(); schedulePreview();
      });
    });
    panel.querySelectorAll('.ccpco-id-manual').forEach(function (inp) {
      inp.addEventListener('input', function () {
        var picker = panel.querySelector('.ccpco-signup-picker[data-attr="' + inp.dataset.attr + '"]');
        if (picker && inp.value.trim()) picker.value = '';
        updateOutput(); schedulePreview();
      });
    });
  });

  // ---- Copy button -------------------------------------------------------
  var copyBtn = document.getElementById('ccpco-copy-btn');
  var copyMsg = document.getElementById('ccpco-copy-msg');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var text = document.getElementById('ccpco-gen-output').textContent;
      navigator.clipboard.writeText(text).then(function () {
        if (copyMsg) {
          copyMsg.style.display = 'inline';
          setTimeout(function () { copyMsg.style.display = 'none'; }, 2000);
        }
      });
    });
  }

  // ---- Settings page copy buttons ----------------------------------------
  document.querySelectorAll('.ccpco-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = btn.previousElementSibling;
      if (!inp) return;
      inp.select(); inp.setSelectionRange(0, 9999);
      try { document.execCommand('copy'); } catch(e) {}
      var orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(function () { btn.textContent = orig; }, 2000);
    });
  });

  // ---- Color text ↔ picker sync on settings page -------------------------
  document.querySelectorAll('.ccpco-color-text').forEach(function (text) {
    var row    = text.closest('td') || text.closest('tr');
    var picker = row ? row.querySelector('input[type="color"]') : null;
    if (!picker) return;
    text.addEventListener('input', function () {
      var v = text.value.trim();
      if (/^#[0-9a-fA-F]{6}$/.test(v)) picker.value = v;
    });
    picker.addEventListener('input', function () { text.value = picker.value; });
  });

  // ---- Live preview ------------------------------------------------------
  var previewTimer = null;
  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(doPreview, 700);
  }
  function doPreview() {
    if (!window.CCPCOAdmin || !CCPCOAdmin.ajax_url) return;
    var sc    = (document.getElementById('ccpco-gen-output') || {}).textContent || '';
    var frame = document.getElementById('ccpco-gen-preview');
    if (!sc || !frame) return;

    frame.innerHTML = '<p style="color:#94a3b8;font-style:italic">Loading preview…</p>';

    var data = new URLSearchParams({
      action:    'ccpco_preview',
      nonce:     CCPCOAdmin.nonce,
      shortcode: sc,
    });

    fetch(CCPCOAdmin.ajax_url, { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        frame.innerHTML = res.success ? res.data : '<p style="color:#dc2626">Preview error.</p>';
      })
      .catch(function () {
        frame.innerHTML = '<p style="color:#dc2626">Preview failed.</p>';
      });
  }

  // ---- Init --------------------------------------------------------------
  populatePickers();
  updateOutput();
  setTimeout(schedulePreview, 800);

})();
