<?php

namespace Drupal\webform_workflows_element_maestro\Plugin\EngineTasks;

use Drupal\maestro\Engine\MaestroEngine;
use Drupal\webform\Entity\WebformSubmission;

/**
 * MaestroTaskTrait.
 *
 * Provides base task parameters and methods.
 * Includes the processID and queueID properties and methods for base task
 * implementation.
 *
 * @ingroup maestro
 */
trait MaestroWebformWorkflowsTrait {

  /**
   * Get webform submission for queue item.
   *
   * @param string $queueID
   *   The queue id.
   *
   * @return \Drupal\webform\Entity\WebformSubmission
   *   The webform submission.
   */
  public static function getSubmission($queueID) {
    // We check if queueID is a token, if so, we get the corresponding id.
    if (!is_numeric($queueID)) {
      $queueID = MaestroEngine::getQueueIdFromToken($queueID);
    }
    $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
    $entity_id = MaestroEngine::getEntityIdentiferByUniqueID($processID, 'submission');
    if ($entity_id) {
      return WebformSubmission::load($entity_id);
    }
    return NULL;
  }

}
