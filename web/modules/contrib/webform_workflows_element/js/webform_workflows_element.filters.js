/**
 * @file
 *
 */

(($, Drupal, drupalSettings) => {
  const settings = drupalSettings.webform_workflows_element;

  const colors = settings ? settings.colors : false;
  const allColorClasses = colors
    ? Object.values(colors).concat(['clear-webform-color'])
    : false;

  const updateElementClass = (element, value) => {
    element.removeClass(allColorClasses.join(' '));
    if (!value) {
      element.addClass('clear-webform-color');
    } else {
      const className = colors ? colors[value] : false;
      if (className) {
        element.addClass(className);
      } else {
        element.addClass('clear-webform-color');
      }
    }
  };

  const updateStylesChosen = (chosen) => {
    /* eslint-disable-next-line no-console */
    console.log('updateStylesChosen', chosen);
    chosen
      .find(
        '.chosen-drop li>span, .chosen-choices li>span, .chosen-single, li.active-result, li.result-selected',
      )
      .each(function process() {
        const value = this.textContent.trim();
        updateElementClass($(this), value);
      });
  };

  const updateStylesSelect = (select) => {
    select.removeClass(allColorClasses.join(' '));
    const value = select.val();
    updateElementClass(select, value);

    // Options
    select.find('option').each(function processOption() {
      const optionValue = this.textContent.trim();
      updateElementClass(select, optionValue);
    });
  };

  $(document).on('ready', () => {
    // Normal selects:
    $('select.webform_workflows_element_filter_states').each(
      function processSelect() {
        const select = $(this);
        updateStylesSelect(select);
        select.on('click', () => {
          updateStylesSelect(select);
        });
        select.on('change', () => {
          updateStylesSelect(select);
        });
      },
    );

    // Chosen:
    const chosen = $(
      '.webform_workflows_element_filter_states.chosen-container',
    );
    if (chosen) {
      updateStylesChosen(chosen);
      chosen.on('click', () => {
        updateStylesChosen(chosen);
      });
      chosen.on('change', () => {
        updateStylesChosen(chosen);
      });
    }
  });
})(jQuery, Drupal, drupalSettings);
