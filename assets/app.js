(function () {
  // ช่องที่มี show_if: แสดงเมื่อช่องที่อ้างถึงมีค่าตามที่กำหนด และปิดการกรอกเมื่อซ่อน (เซิร์ฟเวอร์ตรวจซ้ำอีกชั้น)
  const dependents = document.querySelectorAll('[data-show-field]');
  function valueOf(name) {
    const checked = document.querySelector('[name="' + name + '"]:checked');
    if (checked) return checked.value;
    const el = document.querySelector('[name="' + name + '"]:not([type=radio]):not([type=checkbox])');
    return el ? el.value : '';
  }
  function sync() {
    dependents.forEach(box => {
      const on = box.dataset.showIn.split(',').includes(valueOf(box.dataset.showField));
      box.classList.toggle('is-hidden', !on);
      box.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = !on; });
    });
  }
  if (dependents.length) {
    const watched = new Set([...dependents].map(d => d.dataset.showField));
    watched.forEach(name => document.querySelectorAll('[name="' + name + '"]').forEach(el => el.addEventListener('change', sync)));
    sync();
  }

  document.querySelectorAll('[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  }));
})();
