/* Mamoon Forum — app.js (~1 KB) */
(function () {
  'use strict';

  /* Toggle tema terang/gelap */
  var btn = document.getElementById('theme-toggle');
  if (btn) {
    btn.addEventListener('click', function () {
      var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', cur);
      try { localStorage.setItem('theme', cur); } catch (e) {}
    });
  }

  /* UI upload file: nama file + tombol hapus */
  var input = document.getElementById('fileInput');
  var nameEl = document.getElementById('fileName');
  var clearBtn = document.getElementById('fileClear');
  if (input && nameEl && clearBtn) {
    input.addEventListener('change', function () {
      if (input.files.length > 0) {
        nameEl.textContent = input.files[0].name;
        clearBtn.classList.add('show');
      } else {
        nameEl.textContent = 'Tidak ada file';
        clearBtn.classList.remove('show');
      }
    });
    clearBtn.addEventListener('click', function () {
      input.value = '';
      nameEl.textContent = 'Tidak ada file';
      clearBtn.classList.remove('show');
    });
  }

  /* Toast auto-hide 5 detik */
  document.querySelectorAll('.toast').forEach(function (t) {
    setTimeout(function () {
      t.classList.add('fade-out');
      setTimeout(function () { t.remove(); }, 350);
    }, 5000);
  });
})();
