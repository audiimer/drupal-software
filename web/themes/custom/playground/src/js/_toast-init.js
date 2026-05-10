/* eslint-disable import/no-unresolved, no-restricted-syntax */
import Toast from 'bootstrap/js/dist/toast';

// * Run toasts on page load
(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const toastElList = [...document.querySelectorAll('.toast')];
    for (const toastEl of toastElList) {
      const toast = new Toast(toastEl, { autohide: false });
      toast.show();
    }
  });
})();
