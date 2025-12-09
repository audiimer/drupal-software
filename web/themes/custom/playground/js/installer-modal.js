(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.installerModal = {
    attach: function (context) {
      once("installer-modal", '[id^="installer-modal-"]', context).forEach(
        function (modalEl) {
          var form = modalEl.querySelector(".installer-form");
          if (!form) {
            return;
          }

          var agreeCheckbox = form.querySelector(".agree-checkbox");
          var radios = form.querySelectorAll(
            'input[type="radio"][name="os_selector"]'
          );
          var downloadBtn = modalEl.querySelector(".download-btn");

          function updateState() {
            if (!downloadBtn) return;

            var selected = form.querySelector(
              'input[type="radio"][name="os_selector"]:checked'
            );
            var enabled =
              !!agreeCheckbox &&
              agreeCheckbox.checked &&
              !!selected &&
              !!selected.value;

            downloadBtn.disabled = !enabled;
            downloadBtn.classList.toggle("disabled", !enabled);
          }

          // checkbox + radios control enabled state
          if (agreeCheckbox) {
            agreeCheckbox.addEventListener("change", updateState);
          }
          radios.forEach(function (r) {
            r.addEventListener("change", updateState);
          });

          // click = download selected file
          if (downloadBtn) {
            downloadBtn.addEventListener("click", function (e) {
              // If disabled, do nothing
              if (downloadBtn.disabled) {
                e.preventDefault();
                return;
              }

              var selected = form.querySelector(
                'input[type="radio"][name="os_selector"]:checked'
              );
              if (!selected || !selected.value) {
                e.preventDefault();
                return;
              }

              var url = selected.value;

              // Create a temporary anchor to trigger a real download
              e.preventDefault();
              var link = document.createElement("a");
              link.href = url;
              link.setAttribute("download", "");
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
            });
          }

          // Initial state
          updateState();
        }
      );
    },
  };
})(Drupal, once);
