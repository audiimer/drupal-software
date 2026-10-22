<?php

namespace Drupal\Tests\webform_workflows_element\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests multipage webform submission and submission view HTML.
 *
 * @group your_module
 */
class WebformMultipageSubmissionTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workflows',
    'webform',
    'webform_workflows_element',
  ];

  /**
   * Tests a multipage webform submission.
   */
  public function testMultipageWebformSubmission() {
    $this->drupalLogin($this->rootUser);

    // Define the workflow.
    $workflow = Workflow::create([
      'id' => 'webform_submission_workflow',
      'label' => 'Webform Submission Workflow',
      'type' => 'webform_workflows_element',
      'type_settings' => [
        'states' => [
          'draft' => [
            'label' => 'Draft',
            'weight' => 0,
          ],
          'approved' => [
            'label' => 'Approved',
            'weight' => 1,
          ],
        ],
        'transitions' => [
          'submit_for_approval' => [
            'label' => 'Submit for Approval',
            'from' => ['draft'],
            'to' => 'approved',
            'weight' => 0,
          ],
        ],
      ],
      'entity_type' => 'webform_submission',
      'bundles' => ['multipage_form'],
    ]);
    $workflow->save();

    // Assert it exists and is usable.
    $this->assertEquals('Webform Submission Workflow', $workflow->label());

    // Create the webform.
    $webform = $this->createWebform([
      'id' => 'multipage_form',
      'title' => 'Multipage Form',
    ]);

    // Define elements with pages.
    $elements = [
      'page1' => [
        '#type' => 'webform_wizard_page',
        '#title' => 'Page 1',
        'textfield1' => [
          '#type' => 'textfield',
          '#title' => 'First Name',
        ],
        'workflow' => [
          '#type' => 'webform_workflows_element',
          '#title' => 'Workflow',
          '#workflow' => 'webform_submission_workflow',
          '#log_public_setting' => 'Optional',
          '#log_admin_setting' => 'Optional',
          '#required' => TRUE,
        ],
      ],
      'page2' => [
        '#type' => 'webform_wizard_page',
        '#title' => 'Page 2',
        'textfield2' => [
          '#type' => 'textfield',
          '#title' => 'Last Name',
        ],
      ],
    ];
    $webform->setElements($elements);
    $webform->save();

    // Go to the webform page.
    $this->drupalGet('/webform/multipage_form');

    // Fill out Page 1 and continue.
    $this->submitForm([
      'textfield1' => 'John',
    ], 'Next');

    // Fill out Page 2 and submit.
    $this->submitForm([
      'textfield2' => 'Doe',
    ], 'Submit');

    // Check that the confirmation page contains expected content.
    $this->assertSession()->pageTextContains('New submission added to Multipage Form');

    // Load submissions for the webform.
    $submission_ids = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', 'multipage_form')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $this->assertCount(1, $submission_ids);

    $sid = reset($submission_ids);

    // Go to the submission view page.
    $this->drupalGet("/admin/structure/webform/manage/multipage_form/submission/$sid");

    // Check the rendered HTML output.
    $this->assertSession()->responseContains('<label>First Name</label>');
    $this->assertSession()->responseContains('<label>Last Name</label>');
    $this->assertSession()->responseContains('John');
    $this->assertSession()->responseContains('Doe');

    // Check absence of Next or Previous button.
    $previous_button = $this->cssSelect('input[type="submit"][value="< Previous"]');
    $this->assertEmpty($previous_button, 'Found Previous button');
    $next_button = $this->cssSelect('input[type="submit"][value="Next >"]');
    $this->assertEmpty($next_button, 'Found Next button');

    // Check the presence of the Save workflow button.
    $save_workflow_button = $this->cssSelect('input[type="submit"][value="Save workflow"]');
    $this->assertNotEmpty($save_workflow_button, 'Save workflow button not found');
  }

}
