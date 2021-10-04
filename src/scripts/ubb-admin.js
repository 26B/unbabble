window.addEventListener('load', function() {
  const copyBtnEl = document.getElementById('ubb-translate-action');

  if (copyBtnEl) {
    copyBtnEl.addEventListener('click', function() {
      const targetLangInput = document.querySelector('[name="ubb_create"]');
      const copyLangInput = document.querySelector('[name="ubb_copy"]');

      // FIXME: This should handle query arguments better. Don't assume that any exist.
      window.location.href = `${window.location.href}&ubb_create&ubb_copy=${copyLangInput.value}&lang=${targetLangInput.value}`;
    });
  }
});
