// VioTrace front-end helpers: client-side validation, confirmations, small niceties.
(function () {
  'use strict';

  // Confirm before sensitive form submissions (payments, decisions, transfers).
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) { return; }
      if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  // Client-side validation with inline error styling. The server validates again.
  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.setAttribute('novalidate', 'novalidate');
    form.addEventListener('submit', function (e) {
      var firstBad = null;
      // "At least one of these fields must be filled" (licence number or vehicle number)
      var group = form.getAttribute('data-require-one');
      if (group) {
        var names = group.split(',');
        var filled = names.some(function (n) { var el = form.elements[n]; return el && el.value.trim() !== ''; });
        names.forEach(function (n) {
          var el = form.elements[n];
          if (el) { el.setCustomValidity(filled ? '' : 'Enter a licence number or a vehicle number.'); }
        });
      }
      form.querySelectorAll('input, select, textarea').forEach(function (el) {
        var bad = !el.checkValidity();
        el.classList.toggle('invalid', bad);
        if (bad && !firstBad) { firstBad = el; }
      });
      var pw = form.querySelector('input[name="password"]');
      var cf = form.querySelector('input[name="confirm"]');
      if (pw && cf && pw.value !== cf.value) {
        cf.setCustomValidity('Passwords do not match');
        cf.classList.add('invalid');
        firstBad = firstBad || cf;
      } else if (cf) { cf.setCustomValidity(''); }
      if (firstBad) {
        e.preventDefault();
        firstBad.focus();
        firstBad.reportValidity();
      }
    }, true);
    form.addEventListener('input', function (e) {
      e.target.classList.remove('invalid');
      var g = form.getAttribute('data-require-one');
      if (g) { g.split(',').forEach(function (n) { var el = form.elements[n]; if (el) { el.setCustomValidity(''); el.classList.remove('invalid'); } }); }
    });
  });

  // A violation cannot be in the future: cap the date picker at "now".
  document.querySelectorAll('input[data-max-now]').forEach(function (el) {
    var d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    el.max = d.toISOString().slice(0, 16);
  });

  // Date of birth picker: nobody under 18 can be selected.
  document.querySelectorAll('input[data-adult]').forEach(function (el) {
    var d = new Date();
    d.setFullYear(d.getFullYear() - 18);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    el.max = d.toISOString().slice(0, 10);
  });

  // Upper-case licence numbers and Case IDs as people type or leave the field.
  document.querySelectorAll('input[name="license_no"], input[name="owner_license"], input[name="new_license"], input[name="case_id"]').forEach(function (el) {
    el.addEventListener('blur', function () { el.value = el.value.trim().toUpperCase(); });
  });

  // Keep registration numbers in the same upper-case form the database stores.
  document.querySelectorAll('input[name="reg_no"], input[name="vehicle_no"]').forEach(function (el) {
    el.addEventListener('blur', function () { el.value = el.value.trim().toUpperCase(); });
  });

  // Success messages fade out; errors stay until the page changes.
  document.querySelectorAll('.flash-ok').forEach(function (el) {
    setTimeout(function () { el.style.display = 'none'; }, 8000);
  });
})();
