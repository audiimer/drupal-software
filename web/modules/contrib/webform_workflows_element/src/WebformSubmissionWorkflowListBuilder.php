<?php

namespace Drupal\webform_workflows_element;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\webform\WebformSubmissionListBuilder;
use Drupal\webform_workflows_element\Hook\ResultsFilterHooks;

/**
 * Provides a list controller for webform submission entity.
 *
 * @ingroup webform
 */
class WebformSubmissionWorkflowListBuilder extends WebformSubmissionListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getQuery($keys = '', $state = '', $source_entity = ''): QueryInterface {
    $query = parent::getQuery($keys, $state, $source_entity);

    $query_strings = $this->request->query->all();

    /** @var \Drupal\webform\WebformSubmissionStorageInterface $submission_storage */
    $submission_storage = $this->getStorage();

    $data = [
      'query' => $query,
      'submission_storage' => $submission_storage,
    ];
    ResultsFilterHooks::applyWorkflowQueryFilters($data, $query_strings, $this->webform);
    return $data['query'];
  }

}
