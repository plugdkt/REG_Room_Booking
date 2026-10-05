(function () {
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';

  // ขั้นตอนที่ 1-3 ของคู่มือ DMS: AJAX สร้าง PDF ก่อน แล้วจึง redirect ไป DMS
  document.querySelectorAll('[data-send-dms]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('ยืนยันส่งเอกสารเข้าระบบ DMS?\nหลังจาก DMS รับเอกสารแล้วจะไม่สามารถแก้ไขได้')) return;
      const label = btn.textContent;
      const status = document.getElementById('dms-status');
      btn.disabled = true;
      btn.textContent = 'กำลังสร้างและบันทึกเอกสาร PDF...';
      if (status) { status.className = 'alert alert-warn'; status.textContent = 'กำลังสร้างเอกสาร PDF กรุณารอสักครู่...'; status.hidden = false; }
      try {
        const body = new URLSearchParams({ csrf });
        const res = await fetch(btn.dataset.sendDms, { method: 'POST', body, headers: { 'X-CSRF-Token': csrf } });
        const data = await res.json().catch(() => ({ success: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + res.status + ')' }));
        if (!data.success) throw new Error(data.message || 'สร้างเอกสารไม่สำเร็จ');
        btn.textContent = 'กำลังเปิดระบบ DMS...';
        if (status) { status.className = 'alert alert-ok'; status.textContent = 'สร้างเอกสารสำเร็จ กำลังไปยังระบบ DMS...'; }
        window.location.href = data.redirect;
      } catch (err) {
        btn.disabled = false;
        btn.textContent = label;
        if (status) { status.className = 'alert alert-err'; status.textContent = err.message; status.hidden = false; }
        else alert(err.message);
      }
    });
  });

  // ฟอร์มห้องเรียน: เปิด/ปิดช่องตามประเภทห้องที่เลือก
  const kinds = document.querySelectorAll('input[name=room_kind]');
  function syncRoomKind() {
    document.querySelectorAll('.room-opt').forEach(box => {
      const on = box.querySelector('input[name=room_kind]').checked;
      box.classList.toggle('off', !on);
      box.querySelectorAll('input[type=text]').forEach(i => { i.disabled = !on; });
    });
  }
  kinds.forEach(k => k.addEventListener('change', syncRoomKind));
  if (kinds.length) syncRoomKind();

  // ป้ายกำกับช่องรายละเอียดเปลี่ยนตามวัตถุประสงค์
  const detailLabel = document.getElementById('purpose-detail-label');
  document.querySelectorAll('input[name=purpose]').forEach(r => r.addEventListener('change', () => {
    if (detailLabel) detailLabel.textContent = 'รายละเอียด (' + r.dataset.label + ')';
  }));

  document.querySelectorAll('[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  }));
})();
