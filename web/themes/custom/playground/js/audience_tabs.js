// JS FOR HARDCODED AUDIENCE TABS ON LANDING PAGES NOT BEING USED ANYMORE

(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.audienceTabs = {
    attach: function (context) {
      once("audienceTabs", ".audience-tabs", context).forEach(function (
        wrapper
      ) {
        var buttons = wrapper.querySelectorAll(".tab .tablinks");
        var items = wrapper.querySelectorAll(".audience-item");

        function showAudience(audKey) {
          var cls = "aud-" + audKey;
          items.forEach(function (item) {
            if (item.classList.contains(cls)) {
              item.style.display = "block";
            } else {
              item.style.display = "none";
            }
          });
        }

        // Wire button clicks
        buttons.forEach(function (btn) {
          btn.addEventListener("click", function () {
            var audKey = btn.getAttribute("data-audience");

            // Active state on tabs
            buttons.forEach(function (b) {
              b.classList.remove("active");
            });
            btn.classList.add("active");

            showAudience(audKey);
          });
        });

        // Initial state: first tab
        if (buttons.length > 0) {
          var firstKey = buttons[0].getAttribute("data-audience");
          showAudience(firstKey);
        }
      });
    },
  };
})(Drupal, once);
