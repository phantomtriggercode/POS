/* Motambarea Farmers POS — progressive enhancement only; every form works without JavaScript. */
(function () {
  'use strict';
  var $ = function (s, root) { return (root || document).querySelector(s); };
  var $$ = function (s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); };

  // Mobile menu
  var menuBtn = $('.menu-btn'), nav = $('#sidenav');
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Printing
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  if ($('[data-autoprint]')) { window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); }); }

  // Confirmation (and optional reason) before destructive actions
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (f.hasAttribute('data-reason')) {
        var reason = window.prompt(f.getAttribute('data-confirm') + '\n\nReason:');
        if (!reason || reason.trim().length < 3) { ev.preventDefault(); return; }
        f.querySelector('input[name=reason]').value = reason.trim();
      } else if (!window.confirm(f.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });

  $$('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  // Capture farm GPS from the phone
  $$('[data-gps]').forEach(function (b) {
    if (!navigator.geolocation) { b.hidden = true; return; }
    b.addEventListener('click', function () {
      b.disabled = true; b.textContent = 'Locating…';
      navigator.geolocation.getCurrentPosition(function (pos) {
        b.form.gps_lat.value = pos.coords.latitude.toFixed(6);
        b.form.gps_lng.value = pos.coords.longitude.toFixed(6);
        b.textContent = 'Location captured (±' + Math.round(pos.coords.accuracy) + ' m)'; b.disabled = false;
      }, function () { b.textContent = 'Could not get location'; b.disabled = false; }, { enableHighAccuracy: true, timeout: 20000 });
    });
  });

  // Installer: hide MySQL fields for SQLite
  var driver = $('[data-driver]');
  if (driver) {
    var sync = function () { $$('[data-mysql]').forEach(function (r) { r.hidden = driver.value === 'sqlite'; }); };
    driver.addEventListener('change', sync); sync();
  }

  // Purchase form live totals (preview only — the server recalculates on save)
  var form = $('#purchase-form');
  if (form) {
    var prices = JSON.parse(form.getAttribute('data-prices') || '{}');
    var balance = parseInt(form.getAttribute('data-balance'), 10) || 0;
    var currency = ' XAF';
    var n = function (v) { v = String(v || '').replace(/\s/g, ''); if (v.indexOf(',') > -1 && v.indexOf('.') === -1) v = v.replace(',', '.'); else v = v.replace(/,/g, ''); var x = parseFloat(v); return isNaN(x) ? 0 : x; };
    var fmt = function (x, d) { var p = x.toFixed(d || 0).split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' '); return p.join('.'); };
    var priceInput = $('#price_per_kg');
    var update = function () {
      var official = prices[form.grade_id.value];
      if (priceInput.readOnly) priceInput.value = official ? official : '';
      else priceInput.placeholder = official ? 'official: ' + official : 'no price set';
      var price = priceInput.readOnly ? (official || 0) : (n(priceInput.value) || official || 0);
      var net = Math.round((n(form.gross_kg.value) - n(form.tare_kg.value)) * 100) / 100;
      var value = net > 0 ? Math.round(net * price) : 0;
      var deduct = Math.round(n(form.advance_deducted.value));
      $('#t-net').textContent = net > 0 ? fmt(net, 2) + ' kg' : '—';
      $('#t-value').textContent = value ? fmt(value) + currency : '—';
      $('#t-deduct').textContent = deduct ? '− ' + fmt(deduct) + currency : '0';
      var pay = value - deduct;
      var payEl = $('#t-pay');
      payEl.textContent = value ? fmt(pay) + currency : '—';
      var bad = deduct > balance || deduct > value || deduct < 0;
      form.advance_deducted.setCustomValidity(bad && deduct ? 'Deduction must not exceed what the farmer owes (' + fmt(balance) + ') or the purchase value.' : '');
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  }
})();
