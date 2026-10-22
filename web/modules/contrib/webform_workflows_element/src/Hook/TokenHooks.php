<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;

/**
 * Token hooks for webform_workflows_element.
 */
class TokenHooks {

  use StringTranslationTrait;

  /**
   * Constructs TokenHooks.
   */
  public function __construct(
    protected readonly WebformWorkflowsManager $workflowsManager,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $types = [];
    $types['webform_workflow'] = [
      'name' => $this->t('Webform submissions - workflows'),
      'description' => $this->t('Tokens related to webform submission workflows.'),
    ];

    $webform_submission = [];

    // webform_workflow:transition-url:{element_id}:{transition_id}.
    $webform_submission['transition-url'] = [
      'name' => $this->t('Workflow transition URL'),
      'description' => $this->t('The URL that can used to access the webform submission to update the workflow to this transition - user would need to log in if not already logged in.'),
      'type' => 'url',
      'dynamic' => TRUE,
    ];

    $webform_submission['transition-link'] = [
      'name' => $this->t('Workflow transition link with name of transition'),
      'description' => $this->t('Link to access the webform submission to update the workflow to this transition without logging in. The webform must be configured to allow users to update a submission using a secure token.'),
      'dynamic' => TRUE,
    ];

    $webform_submission['transition-url-secure-token'] = [
      'name' => $this->t('Workflow transition URL via secure token'),
      'description' => $this->t('The URL that can used to access the webform submission to update the workflow to this transition without logging in. The webform must be configured to allow users to update a submission using a secure token.'),
      'type' => 'url',
      'dynamic' => TRUE,
    ];

    $webform_submission['transition-link-secure-token'] = [
      'name' => $this->t('Workflow transition link with name of transition'),
      'description' => $this->t('Link to access the webform submission to update the workflow to this transition without logging in. The webform must be configured to allow users to update a submission using a secure token.'),
      'dynamic' => TRUE,
    ];

    return [
      'types' => $types,
      'tokens' => [
        'webform_workflow' => $webform_submission,
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(
    $type,
    $tokens,
    array $data,
    array $options,
    BubbleableMetadata $bubbleable_metadata,
  ): array {

    $replacements = [];
    if ($type === 'webform_workflow' && !empty($data['webform_submission'])) {
      // Adding webform submission, webform, source entity to bubbleable meta.
      // This reduces code duplication and easier to track.
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $data['webform_submission'];
      $bubbleable_metadata->addCacheableDependency($webform_submission);

      // A valid id is required to generate an entity URL.
      // See https://www.drupal.org/i/3556600
      $has_id = $webform_submission->id() !== NULL;

      foreach ($tokens as $name => $original) {
        $values = explode(':', $name);
        $first_value = reset($values);
        switch ($first_value) {
          case 'transition-url':
          case 'transition-url-secure-token':
            $replacements[$original] =
              $has_id
                ? $this->getUrlToTransition($name, $options, $webform_submission)
                : '';
            break;

          case 'transition-link':
          case 'transition-link-secure-token':
            // Get label for transition:
            $keys = explode(':', $name);
            $element_id = $keys[1];
            $transition_id = $keys[2];
            $element = $webform_submission->getWebform()
              ->getElementDecoded($element_id);
            $workflow_type = $this->workflowsManager->getWorkflowTypeFromElement($element);

            // It's unclear why $workflow_type wouldn't be set here,
            // but we fail gracefully by showing the transition ID.
            if ($workflow_type) {
              $transition = $workflow_type->getTransition($transition_id);
              $label = $transition->label();
            }
            else {
              $label = $transition_id;
            }

            if ($has_id) {
              // Get URL:
              $url = $this->getUrlToTransition($name, $options, $webform_submission);

              // Return basic link:
              $replacements[$original] = Markup::create('<a href="' . $url . '">' . $label . '</a>');
            }
            else {
              // Return label:
              $replacements[$original] = Markup::create($label);
            }
            break;

          default:
            break;
        }
      }
    }

    return $replacements;
  }

  /**
   * Converts a link token name to a URL.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function getUrlToTransition(
    string $name,
    array $options,
    WebformSubmissionInterface $webform_submission,
  ): string {
    // webform-workflows:transition-link:{element_id}:{transition_id}.
    $keys = explode(':', $name);
    $token = $keys[0];
    $element_id = $keys[1];
    $transition_id = $keys[2];

    // Get base URL:
    if ($token === 'transition-url-secure-token') {
      $url = $webform_submission->getTokenUrl('canonical');
    }
    else {
      $url = $webform_submission->toUrl('canonical');
    }

    $url->setAbsolute(TRUE);

    // Set query string:
    $url->setOption('query', ($url->getOption('query') ?? []) + [
      'workflow_element' => $element_id,
      'transition' => $transition_id,
    ]);

    return $url->toString();
  }

}
