<?php

namespace Drupal\lts_chargeback\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Chargeback Information checkout pane.
 *
 * Captures the billing details required for index-based chargeback orders:
 * requester name, email, department, index number, and authorized signer.
 * Data is saved directly onto the commerce_order entity via base fields
 * defined in lts_chargeback.module.
 *
 * The pane is only visible when the cart contains at least one order item
 * referencing a Software node with field_access_type = 'chargeback'.
 *
 * @CommerceCheckoutPane(
 *   id = "lts_chargeback_information",
 *   label = @Translation("Chargeback information"),
 *   default_step = "order_information",
 * )
 */
class ChargebackInformation extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return $this->orderHasChargebackItem();
  }

  /**
   * Checks whether the current order has any chargeback-access software.
   *
   * @return bool
   *   TRUE if at least one order item's purchased variation references
   *   a Software node with field_access_type = 'chargeback'.
   */
  protected function orderHasChargebackItem() {
    foreach ($this->order->getItems() as $order_item) {
      $variation = $order_item->getPurchasedEntity();
      if (!$variation || !$variation->hasField('field_software')) {
        continue;
      }
      $software = $variation->get('field_software')->entity;
      if (!$software || !$software->hasField('field_access_type')) {
        continue;
      }
      if ($software->get('field_access_type')->value === 'chargeback') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $summary = [];

    if ($name = $this->order->get('chargeback_name')->value) {
      $summary['name'] = [
        '#type' => 'item',
        '#title' => $this->t('Requester'),
        '#markup' => $name,
      ];
    }
    if ($email = $this->order->get('chargeback_email')->value) {
      $summary['email'] = [
        '#type' => 'item',
        '#title' => $this->t('Email'),
        '#markup' => $email,
      ];
    }
    if ($dept = $this->order->get('chargeback_department')->value) {
      $summary['department'] = [
        '#type' => 'item',
        '#title' => $this->t('Department'),
        '#markup' => $dept,
      ];
    }
    if ($index = $this->order->get('chargeback_index')->value) {
      $summary['index'] = [
        '#type' => 'item',
        '#title' => $this->t('Index'),
        '#markup' => $index,
      ];
    }
    if ($signer = $this->order->get('chargeback_authorized_signer')->value) {
      $summary['signer'] = [
        '#type' => 'item',
        '#title' => $this->t('Authorized signer'),
        '#markup' => $signer,
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['#attributes']['class'][] = 'lts-chargeback-pane';

    $pane_form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('This order requires chargeback billing. Please provide the index and authorization details below.'),
    ];

    $pane_form['chargeback_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorized requester name'),
      '#default_value' => $this->order->get('chargeback_name')->value,
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $pane_form['chargeback_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Lehigh email address'),
      '#default_value' => $this->order->get('chargeback_email')->value,
      '#required' => TRUE,
      '#description' => $this->t('Used to send confirmation and follow-up.'),
    ];

    $pane_form['chargeback_department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#default_value' => $this->order->get('chargeback_department')->value,
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $pane_form['chargeback_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index number'),
      '#default_value' => $this->order->get('chargeback_index')->value,
      '#required' => TRUE,
      '#maxlength' => 64,
      '#description' => $this->t('The Banner index number that will be charged.'),
    ];

    $pane_form['chargeback_authorized_signer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorized signer'),
      '#default_value' => $this->order->get('chargeback_authorized_signer')->value,
      '#required' => TRUE,
      '#maxlength' => 255,
      '#description' => $this->t('Person authorized to approve charges to this index.'),
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    // Light index-number sanity check. Banner indexes are typically 6 chars,
    // alphanumeric. Adjust if Lehigh's format is stricter.
    if (!empty($values['chargeback_index']) && !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $values['chargeback_index'])) {
      $form_state->setError(
        $pane_form['chargeback_index'],
        $this->t('Index number should be 3-20 alphanumeric characters.')
      );
    }

    // Email domain check — Lehigh email expected.
    if (!empty($values['chargeback_email']) && !str_ends_with(strtolower($values['chargeback_email']), '@lehigh.edu')) {
      $form_state->setError(
        $pane_form['chargeback_email'],
        $this->t('Please use a @lehigh.edu email address.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    $this->order->set('chargeback_name', $values['chargeback_name']);
    $this->order->set('chargeback_email', $values['chargeback_email']);
    $this->order->set('chargeback_department', $values['chargeback_department']);
    $this->order->set('chargeback_index', $values['chargeback_index']);
    $this->order->set('chargeback_authorized_signer', $values['chargeback_authorized_signer']);
  }

}
