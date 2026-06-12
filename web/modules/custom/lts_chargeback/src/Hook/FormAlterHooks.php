<?php

namespace Drupal\lts_chargeback\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alter hooks for lts_chargeback.
 *
 * Adds a denial reason field to the state machine transition confirmation
 * form when an admin is denying a chargeback order. The reason is saved to
 * the order's denial_reason field and displayed to the user on their order
 * page via the chargeback gate block.
 *
 * POC STUB: In production, this would also trigger a notification email to
 * the user. Currently logs to watchdog only.
 */
class FormAlterHooks {

  /**
   * Adds a denial reason textarea to the deny transition confirmation modal.
   *
   * Only fires for the 'deny' transition on commerce_order entities, so
   * approve/cancel transitions are unaffected.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Target only the state machine transition confirm form. The full form
    // ID is entity-derived (e.g. commerce_order_chargeback_state-transition-
    // confirm_form), so match on the stable base form ID instead.
    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getBaseFormId') || $form_object->getBaseFormId() !== 'state_machine_transition_confirm_form') {
      return;
    }

    // Only add the field when the transition is 'deny'.
    if (!method_exists($form_object, 'getTransition')) {
      return;
    }
    $transition = $form_object->getTransition();
    if (!$transition || $transition->getId() !== 'deny') {
      return;
    }

    // Only act on commerce_order entities.
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity || $entity->getEntityTypeId() !== 'commerce_order') {
      return;
    }

    // Add the denial reason field above the confirm/cancel buttons.
    $form['denial_reason'] = [
      '#type' => 'textarea',
      '#title' => t('Denial reason'),
      '#description' => t('Required. This message will be shown to the user on their order page.'),
      '#required' => TRUE,
      '#rows' => 3,
      '#weight' => 0,
      '#default_value' => $entity->hasField('denial_reason') ? $entity->get('denial_reason')->value : '',
    ];

    // Move the action buttons below our field.
    if (isset($form['actions'])) {
      $form['actions']['#weight'] = 10;
    }

    // Add our submit handler BEFORE the default one so we can set the field
    // value on the entity before it gets saved by the parent submitForm().
    array_unshift($form['actions']['submit']['#submit'], [static::class, 'submitDenialReason']);
  }

  /**
   * Submit handler: saves the denial reason to the order before transition.
   *
   * Runs before StateTransitionConfirmForm::submitForm(), which calls
   * $entity->save() — so our value is persisted in the same save.
   *
   * POC STUB: Replace \Drupal::logger() call with a real email notification
   * to the order's customer email in production.
   */
  public static function submitDenialReason(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity || $entity->getEntityTypeId() !== 'commerce_order') {
      return;
    }

    $reason = $form_state->getValue('denial_reason');
    if ($reason && $entity->hasField('denial_reason')) {
      $entity->set('denial_reason', $reason);
      // POC STUB: log instead of emailing. Replace with Symfony Mailer in prod.
      \Drupal::logger('lts_chargeback')->notice(
        'Order @id denied. Reason: @reason. Customer: @email',
        [
          '@id' => $entity->id(),
          '@reason' => $reason,
          '@email' => $entity->getEmail(),
        ]
      );
    }
  }

}
