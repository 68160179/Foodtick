// Recipe form: add/remove ingredient & step rows, image preview
(function () {
  function renumber() {
    document.querySelectorAll('#step-rows .step-no').forEach(function (el, i) { el.textContent = i + 1; });
  }

  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add]');
    if (add) {
      var box = document.getElementById(add.dataset.add);
      var row = box.lastElementChild.cloneNode(true);
      row.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
      box.appendChild(row);
      row.querySelector('input, textarea').focus();
      renumber();
      return;
    }
    var rm = e.target.closest('[data-remove]');
    if (rm) {
      var rowEl = rm.closest('.row');
      if (rowEl.parentElement.children.length > 1) rowEl.remove();
      else rowEl.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
      renumber();
    }
  });

  var file = document.getElementById('image');
  if (file) {
    file.addEventListener('change', function () {
      var img = document.getElementById('preview');
      var text = document.getElementById('dz-text');
      if (file.files && file.files[0]) {
        img.src = URL.createObjectURL(file.files[0]);
        img.hidden = false; text.hidden = true;
      }
    });
  }
  renumber();

  // Delete confirmation for forms with data-confirm. Uses an in-page <dialog> because
  // native confirm() is silently blocked in some embedded browsers (the form never submits).
  var dialog = document.getElementById('confirm-dialog');
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.dataset || !form.dataset.confirm || form.dataset.confirmed) return;
    if (!dialog || typeof dialog.showModal !== 'function') return; // no <dialog> support: just submit
    e.preventDefault();
    document.getElementById('confirm-text').textContent = form.dataset.confirm;
    dialog.onclose = function () {
      if (dialog.returnValue !== 'ok') return;
      form.dataset.confirmed = '1';
      form.submit();
    };
    dialog.returnValue = '';
    dialog.showModal();
    document.getElementById('confirm-ok').focus();
  });

  // Profile photo preview + "remove photo" toggle on the edit-profile page
  var avatarInput = document.getElementById('avatar');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      if (!avatarInput.files || !avatarInput.files[0]) return;
      var box = document.getElementById('avatar-preview');
      box.innerHTML = '';
      var img = document.createElement('img');
      img.className = 'avatar xl'; img.alt = '';
      img.src = URL.createObjectURL(avatarInput.files[0]);
      box.appendChild(img);
      var rm = document.getElementById('remove_avatar');
      if (rm) rm.checked = false;
    });
  }

  // Like / save buttons: update in place without reloading (falls back to a normal POST without JS)
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.js-toggle');
    if (!form || !window.fetch) return;
    e.preventDefault();
    var btn = form.querySelector('button');
    btn.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.login) { location.href = data.login; return; }
        if (typeof data.count !== 'number') return;
        btn.classList.toggle('on', data.active);
        btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
        btn.querySelector('.act-count').textContent = data.count;
        if (data.active) { btn.classList.remove('pop'); void btn.offsetWidth; btn.classList.add('pop'); }
      })
      .catch(function () { form.submit(); })
      .finally(function () { btn.disabled = false; });
  });
})();
