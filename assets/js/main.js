/**
 * 予約管理システム JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

  // ============================================================
  // 時間スロット選択（ラジオボタンのスタイル同期）
  // ============================================================
  const timeSlots = document.querySelectorAll('.time-slot');
  timeSlots.forEach(function (slot) {
    const radio = slot.querySelector('input[type=radio]');
    if (!radio) return;

    // 初期状態
    if (radio.checked) slot.classList.add('selected');

    slot.addEventListener('click', function () {
      timeSlots.forEach(function (s) { s.classList.remove('selected'); });
      slot.classList.add('selected');
    });
  });

  // ============================================================
  // メニューカード選択（ラジオボタン）
  // ============================================================
  const menuCards = document.querySelectorAll('.menu-card');
  menuCards.forEach(function (card) {
    const radio = card.querySelector('.menu-radio');
    if (!radio) return;

    if (radio.checked) card.classList.add('selected');

    card.addEventListener('click', function () {
      menuCards.forEach(function (c) { c.classList.remove('selected'); });
      card.classList.add('selected');
    });
  });

  // ============================================================
  // フォームの二重送信防止
  // ============================================================
  const forms = document.querySelectorAll('form');
  forms.forEach(function (form) {
    form.addEventListener('submit', function () {
      const submitBtns = form.querySelectorAll('button[type=submit]');
      submitBtns.forEach(function (btn) {
        // confirmダイアログがあるフォームは除外
        if (form.getAttribute('onsubmit')) return;
        setTimeout(function () {
          btn.disabled = true;
          btn.textContent = '送信中...';
        }, 100);
      });
    });
  });

  // ============================================================
  // ツールチップ初期化（Bootstrap 5）
  // ============================================================
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

  // ============================================================
  // 日付入力: 今日以前を選択した場合に警告
  // ============================================================
  const dateInput = document.querySelector('input[name=date]');
  if (dateInput) {
    dateInput.addEventListener('change', function () {
      const today = new Date().toISOString().split('T')[0];
      if (this.value < today) {
        this.setCustomValidity('過去の日付は選択できません');
        this.reportValidity();
        this.value = '';
      } else {
        this.setCustomValidity('');
      }
    });
  }

});
