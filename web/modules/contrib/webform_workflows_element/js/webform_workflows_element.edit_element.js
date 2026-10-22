/**
 * @file
 *
 * UI on the element editing form
 *
 */

(($, Drupal) => {
  Drupal.behaviors.hideShowAccess = {
    attach() {
      Drupal.webform = Drupal.webform || {};

      function updateAccessFromCheckbox(checkbox) {
        /* eslint-disable-next-line no-console */
        console.log('updateAccessFromCheckbox', checkbox);
        const wrapper = checkbox.closest('.details-wrapper');
        if (checkbox.is(':checked')) {
          wrapper.children().show();
        } else {
          wrapper.children().hide();
        }
        checkbox.parent().show();
        wrapper.find('[id$="override"]').parent().show();
      }

      const $workflowEnabled = $('[id$="workflow-enabled"]');
      $workflowEnabled.on('change', function onChange() {
        updateAccessFromCheckbox($(this));
      });
      const $override = $('[id$="override"]');
      $override.on('change', function onChange() {
        updateAccessFromCheckbox($(this));
      });

      $workflowEnabled.trigger('change');
      $override.trigger('change');
    },
  };
})(jQuery, Drupal);
