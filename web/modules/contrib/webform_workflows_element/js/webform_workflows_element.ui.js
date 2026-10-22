/**
 * @file
 *
 * UI on the form itself
 *
 */

(($, Drupal) => {
  Drupal.behaviors.hideShowLogs = {
    attach() {
      Drupal.webform = Drupal.webform || {};

      // If transition changes, show log fields, otherwise hide
      $(
        'select.workflow-transition.form-select, fieldset.workflow-transition.form-composite',
      ).on('change', function onChange() {
        const selectedOrChecked = $(this).find(':selected, :checked');
        const val = selectedOrChecked.length > 0 ? selectedOrChecked.val() : '';
        const logs = $(
          '.form-item--workflow-log-public, .form-item--workflow-log-admin, .form-item-workflow-log-public, .form-item-workflow-log-admin',
        );
        if (!val || val === '') {
          logs.slideUp();
          logs.find('textarea').val('');
        } else {
          logs.slideDown();
        }
      });

      $(document).on('ready', () => {
        $(
          '.workflow-transition.form-select, .workflow-transition.form-composite',
        ).trigger('change');
      });
    },
  };
})(jQuery, Drupal);
