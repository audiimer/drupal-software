<?php

namespace Drupal\lts_software\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\Plugin\Field\FieldFormatter\PriceDefaultFormatter;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Price formatter that renders "Free" for zero values.
 *
 * Extends the default Commerce price formatter so all currency formatting
 * configuration (currency display, strip trailing zeros, etc.) is preserved
 * for non-zero prices. Zero prices render as the localized "Free" string.
 *
 * Use this formatter via Manage Display wherever a commerce_price field
 * should show "Free" instead of "$0.00":
 *   - Commerce product variation default display (cart, checkout, summary)
 *   - Catalog cards (applied programmatically in playground.theme)
 *   - Anywhere SoftwarePriceBlock renders the variation price
 *
 * @FieldFormatter(
 *   id = "commerce_price_free",
 *   label = @Translation("Default + Free label for zero"),
 *   field_types = {
 *     "commerce_price"
 *   }
 * )
 */
class CommercePriceFreeFormatter extends PriceDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      // Defensive: bail to parent if shape is unexpected.
      if (empty($item->number) && $item->number !== '0' && $item->number !== 0) {
        $parent = parent::viewElements($items, $langcode);
        return $parent;
      }

      // Treat anything numerically zero (0, 0.00, "0.000000") as Free.
      if ((float) $item->number == 0.0) {
        $elements[$delta] = [
          '#markup' => $this->t('Free'),
          '#cache' => [
            'contexts' => ['languages:language_interface'],
          ],
        ];
      }
      else {
        // Reuse the default formatter for the single item.
        $single_list = clone $items;
        $single_list->setValue([$item->getValue()]);
        $default = parent::viewElements($single_list, $langcode);
        $elements[$delta] = $default[0] ?? ['#markup' => ''];
      }
    }

    return $elements;
  }

}