<?php

namespace Drupal\Tests\webform_workflows_element\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a full workflow lifecycle with role-based access.
 *
 * Simulates a real-life submission workflow with three users:
 * an authorized user (no special role, denied access), a
 * submitter who creates and submits for review, and a
 * reviewer who can approve or reject. Covers the full
 * cycle: create, submit, reject, resubmit, and approve.
 * Verifies role-based transition access, log messages, and
 * access denial for unauthorized users.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WorkflowLifecycleTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workflows',
    'webform',
    'webform_workflows_element',
    // Required for the workflow log table to appear on the
    // submission view/edit pages. Without this module,
    // shouldShowWorkflowLog() always returns FALSE.
    'webform_submission_log',
  ];

  /**
   * The test workflow entity.
   *
   * @var \Drupal\workflows\WorkflowInterface
   */
  protected $workflow;

  /**
   * The test webform with a workflow element.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $testWebform;

  /**
   * An authenticated user with no special roles.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorizedUser;

  /**
   * A user with the 'submitter' role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $submitterUser;

  /**
   * A user with the 'reviewer' role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $reviewerUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the workflow with three states and three
    // transitions. initial_state is set to 'draft' so new
    // submissions (where the workflow element was hidden on
    // the create form) default to the draft state when
    // rendered on the edit/view page.
    //
    // State graph:
    // phpcs:disable Drupal.Commenting.InlineComment
    //   draft --(submit)--> review --(approve)--> approved
    //                         \----(reject)----> draft
    // phpcs:enable Drupal.Commenting.InlineComment
    //
    // 'approved' is a terminal state with no outgoing
    // transitions. This means once approved, nobody sees
    // the workflow form (no available transitions).
    $this->workflow = Workflow::create([
      'id' => 'lifecycle_workflow',
      'label' => 'Lifecycle Workflow',
      'type' => 'webform_workflows_element',
      'type_settings' => [
        'initial_state' => 'draft',
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0],
          'review' => ['label' => 'Review', 'weight' => 1],
          'approved' => ['label' => 'Approved', 'weight' => 2],
        ],
        'transitions' => [
          'submit' => [
            'label' => 'Submit for review',
            'from' => ['draft'],
            'to' => 'review',
            'weight' => 0,
          ],
          'approve' => [
            'label' => 'Approve',
            'from' => ['review'],
            'to' => 'approved',
            'weight' => 1,
          ],
          'reject' => [
            'label' => 'Reject',
            'from' => ['review'],
            'to' => 'draft',
            'weight' => 2,
          ],
        ],
      ],
    ]);
    $this->workflow->save();

    // Create the 'submitter' and 'reviewer' roles. The
    // authorized user has no extra roles — just the base
    // 'authenticated' role that all logged-in users get.
    Role::create([
      'id' => 'submitter',
      'label' => 'Submitter',
    ])->save();
    Role::create([
      'id' => 'reviewer',
      'label' => 'Reviewer',
    ])->save();

    // Create three test users. All get 'access content' as
    // the base permission. 'access webform overview' lets
    // them reach admin submission pages (the route still
    // enforces entity-level access via per-webform rules).
    $base_permissions = [
      'access content',
      'access webform overview',
    ];

    // The authorized user has NO special roles. Per-webform
    // rules will deny them both create and view access.
    $this->authorizedUser = $this->drupalCreateUser(
      $base_permissions,
    );

    // The submitter can create submissions and perform the
    // 'submit' transition. They can view/update their OWN
    // submissions but not others'.
    $this->submitterUser = $this->drupalCreateUser(
      $base_permissions,
    );
    $this->submitterUser->addRole('submitter');
    $this->submitterUser->save();

    // The reviewer can view/update ANY submission and perform
    // 'approve' and 'reject' transitions. They cannot create
    // new submissions.
    $this->reviewerUser = $this->drupalCreateUser(
      $base_permissions,
    );
    $this->reviewerUser->addRole('reviewer');
    $this->reviewerUser->save();

    // Create the webform as root user (admin access needed
    // for webform creation).
    $this->drupalLogin($this->rootUser);
    $this->testWebform = $this->createWebform([
      'id' => 'test_lifecycle',
      'title' => 'Lifecycle Test Form',
    ]);

    // Define form elements: a text field for the actual
    // submission content, and the workflow element.
    //
    // Workflow element configuration:
    // - Hidden on create form (default, #access_create is
    //   FALSE). The submitter fills only regular fields.
    // - #access_update_roles defaults to ['authenticated'],
    //   so both submitter and reviewer can see the element
    //   on the edit/view form.
    // - Transition access is role-restricted:
    //   'submit' → submitter only
    //   'approve' / 'reject' → reviewer only
    // - Both log fields enabled as optional. The public log
    //   ("Log message for submitter") is intended for the
    //   submitter. The admin log ("Log message - admin only")
    //   is intended for reviewers. Note: the module shows
    //   both textareas to any user who can see the element.
    $this->testWebform->setElements([
      'name' => [
        '#type' => 'textfield',
        '#title' => 'Full name',
        '#required' => TRUE,
      ],
      'workflow' => [
        '#type' => 'webform_workflows_element',
        '#title' => 'Workflow',
        '#workflow' => 'lifecycle_workflow',
        '#log_public_setting' => 'Optional',
        '#log_admin_setting' => 'Optional',
        '#access_transition_submit_roles' => ['submitter'],
        '#access_transition_approve_roles' => ['reviewer'],
        '#access_transition_reject_roles' => ['reviewer'],
        // Lock submission editing in review state: only the
        // reviewer role can edit. The submitter gets 403 when
        // trying to open the edit form or the view page.
        '#allow_restricting_editing_by_state' => TRUE,
        '#access_update_at_state_review_override' => TRUE,
        '#access_update_at_state_review_roles' => ['reviewer'],
      ],
    ]);

    // Per-webform access rules control who can interact with
    // submissions at the entity level:
    // - create: only submitter role (authorized user denied).
    // - view_own / update_own: only submitter (own subs).
    // - view_any / update_any: only reviewer (all subs).
    // These rules work alongside the element-level transition
    // roles to provide layered access control.
    $rules = $this->testWebform->getAccessRules();
    $rules['create'] = [
      'roles' => ['submitter'],
      'users' => [],
      'permissions' => [],
    ];
    $rules['view_own'] = [
      'roles' => ['submitter'],
      'users' => [],
      'permissions' => [],
    ];
    $rules['update_own'] = [
      'roles' => ['submitter'],
      'users' => [],
      'permissions' => [],
    ];
    $rules['view_any'] = [
      'roles' => ['reviewer'],
      'users' => [],
      'permissions' => [],
    ];
    $rules['update_any'] = [
      'roles' => ['reviewer'],
      'users' => [],
      'permissions' => [],
    ];
    $this->testWebform->setAccessRules($rules);

    // Enable submission logging so the workflow log table
    // appears on view/edit pages. The webform must have
    // submission_log enabled AND the webform_submission_log
    // module must be installed (done via $modules above).
    $this->testWebform->setSetting('submission_log', TRUE);

    $this->testWebform->save();
    $this->drupalLogout();
  }

  /**
   * Tests the full workflow lifecycle with role-based access.
   *
   * Walks through a complete real-life scenario across nine
   * phases: access denial for unauthorized user, submission
   * creation by submitter, submit for review, submitter
   * locked out by state-based edit access in review state,
   * reviewer sees approve/reject, reviewer rejects with log
   * messages, submitter regains access and resubmits,
   * reviewer approves, and terminal state with no
   * transitions.
   */
  public function testWorkflowLifecycle(): void {
    // =========================================================
    // PART 1: Authorized user (no special role) is denied.
    // =========================================================
    // An authenticated user without the 'submitter' role is
    // not listed in the webform's 'create' access rules, so
    // the form page should return 403 Access Denied.
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/webform/test_lifecycle');
    $this->assertSession()->statusCodeEquals(403);

    // =========================================================
    // PART 2: Submitter creates a new submission.
    // =========================================================
    // The submitter has the 'submitter' role which IS listed
    // in the webform's 'create' access rules.
    $this->drupalLogin($this->submitterUser);
    $this->drupalGet('/webform/test_lifecycle');
    $this->assertSession()->statusCodeEquals(200);

    // The workflow element should NOT appear on the create
    // form because #access_create_workflow_enabled defaults
    // to FALSE. Only regular form fields are shown.
    $this->assertSession()->fieldNotExists(
      'workflow[transition]',
    );

    // Submit the form with the required text field.
    $this->submitForm([
      'Full name' => 'Alice Testuser',
    ], 'Submit');

    // Verify the submission was created successfully.
    $this->assertSession()->pageTextContains(
      'New submission added to Lifecycle Test Form',
    );

    // Retrieve the submission ID from the database.
    $sids = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', 'test_lifecycle')
      ->accessCheck(FALSE)
      ->sort('sid', 'DESC')
      ->range(0, 1)
      ->execute();
    $this->assertNotEmpty($sids, 'Submission should exist.');
    $sid = reset($sids);

    // Admin view URL for the submission. The route checks
    // entity-level access (per-webform rules), not just
    // admin permissions.
    $view_url = "/admin/structure/webform/manage"
      . "/test_lifecycle/submission/$sid";

    // Verify the authorized user cannot view the submission
    // either (not in view_own or view_any rules).
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(403);

    // =========================================================
    // PART 3: Submitter submits for review (draft → review).
    // =========================================================
    // On the submission view page, the module renders a
    // reduced workflow form (just the workflow element +
    // "Save workflow" button) when the user has available
    // transitions. Since the workflow element was hidden on
    // create, the submission starts with no workflow data.
    // The element defaults to the initial state ('draft')
    // and shows the 'Submit for review' transition.
    $this->drupalLogin($this->submitterUser);
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(200);

    // Verify the reduced workflow form is present.
    $this->assertSession()->buttonExists('Save workflow');

    // Verify the submitter sees ONLY 'Submit for review'.
    // The 'Approve' and 'Reject' transitions are restricted
    // to the 'reviewer' role via #access_transition_*_roles,
    // so they should not appear in the dropdown.
    $transition_select = $this->assertSession()
      ->selectExists('workflow[transition]');
    $this->assertNotNull(
      $transition_select->find('css', 'option[value="submit"]'),
      'Submitter should see the Submit transition.',
    );
    $this->assertNull(
      $transition_select->find('css', 'option[value="approve"]'),
      'Submitter should NOT see the Approve transition.',
    );
    $this->assertNull(
      $transition_select->find('css', 'option[value="reject"]'),
      'Submitter should NOT see the Reject transition.',
    );

    // Select 'Submit for review' and add a public log message
    // (the "Log message for submitter" field). Field names
    // use the composite element naming: workflow[sub_element].
    // The workflow_fieldset container is transparent (does not
    // add a nesting level to form names).
    $this->submitForm([
      'workflow[transition]' => 'submit',
      'workflow[log_public]' => 'Ready for review',
    ], 'Save workflow');

    // Verify the workflow state changed to 'review'.
    //
    // We use resetCache() + load() instead of loadUnchanged()
    // because of a webform bug: when loadUnchanged() finds
    // the entity in the persistent cache, it calls postLoad()
    // but NOT doLoadMultiple() — which is where webform's
    // loadData() populates submission data from the
    // webform_submission_data table. The result is an entity
    // with empty element data. Using resetCache() (which
    // deletes the persistent cache entry) followed by load()
    // forces the full doLoadMultiple() → loadData() path.
    $storage = \Drupal::entityTypeManager()
      ->getStorage('webform_submission');
    $storage->resetCache([$sid]);
    $submission = $storage->load($sid);
    $element_data = $submission->getElementData('workflow');
    $this->assertSame('review', $element_data['workflow_state']);

    // =========================================================
    // PART 4: Submitter locked out in review state.
    // =========================================================
    // The workflow element has
    // #access_update_at_state_review_override = TRUE with
    // #access_update_at_state_review_roles = ['reviewer'].
    // The state-based access override only applies to the
    // 'update' operation, so the submitter can still VIEW
    // the submission but cannot EDIT it.
    $edit_url = $view_url . '/edit';

    // View page: allowed (view_own access still applies).
    // But the reduced workflow form (Save workflow button)
    // should NOT appear because the submitter has zero
    // available transitions from the 'review' state.
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonNotExists('Save workflow');

    // Edit page: forbidden by state-based access override.
    $this->drupalGet($edit_url);
    $this->assertSession()->statusCodeEquals(403);

    // =========================================================
    // PART 5: Reviewer sees approve and reject.
    // =========================================================
    // The reviewer has the 'reviewer' role. From 'review',
    // both 'Approve' and 'Reject' are available to them.
    // 'Submit for review' should NOT appear: it's only valid
    // from 'draft' state AND restricted to submitter role.
    $this->drupalLogin($this->reviewerUser);
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save workflow');

    $transition_select = $this->assertSession()
      ->selectExists('workflow[transition]');
    $this->assertNotNull(
      $transition_select->find('css', 'option[value="approve"]'),
      'Reviewer should see the Approve transition.',
    );
    $this->assertNotNull(
      $transition_select->find('css', 'option[value="reject"]'),
      'Reviewer should see the Reject transition.',
    );
    $this->assertNull(
      $transition_select->find('css', 'option[value="submit"]'),
      'Reviewer should NOT see the Submit transition.',
    );

    // The reviewer should also see the log from the
    // submitter's initial submission.
    $this->assertSession()->pageTextContains('Ready for review');

    // =========================================================
    // PART 6: Reviewer rejects (review → draft).
    // =========================================================
    // The reviewer selects 'Reject' and adds both log
    // messages: a public log (visible to the submitter in
    // the log table) and an admin-only log (intended for
    // internal tracking).
    $this->submitForm([
      'workflow[transition]' => 'reject',
      'workflow[log_public]' => 'Please fix section 3',
      'workflow[log_admin]' => 'Missing required fields',
    ], 'Save workflow');

    // Verify the state reverted to 'draft'.
    $storage->resetCache([$sid]);
    $submission = $storage->load($sid);
    $element_data = $submission->getElementData('workflow');
    $this->assertSame('draft', $element_data['workflow_state']);

    // =========================================================
    // PART 7: Submitter sees rejection and resubmits.
    // =========================================================
    // Back in 'draft', the state-based edit override no
    // longer applies (only 'review' has an override). The
    // submitter regains access and has the 'Submit for
    // review' transition again. The workflow log table should
    // be visible (submitter has update access + available
    // transitions) and contain the reviewer's public log
    // message from the rejection.
    $this->drupalLogin($this->submitterUser);
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save workflow');

    // Verify 'Submit for review' is available again.
    $transition_select = $this->assertSession()
      ->selectExists('workflow[transition]');
    $this->assertNotNull(
      $transition_select->find('css', 'option[value="submit"]'),
      'Submitter should see Submit after rejection.',
    );

    // Verify the reviewer's public log message from the
    // rejection is visible in the rendered workflow log.
    // Note: the module renders both public and admin log
    // messages in the same log entry. The submitter can
    // also see the admin message here — this is the
    // module's current behavior.
    $this->assertSession()->pageTextContains(
      'Please fix section 3',
    );

    // Submitter resubmits with an updated public log.
    $this->submitForm([
      'workflow[transition]' => 'submit',
      'workflow[log_public]' => 'Section 3 completed',
    ], 'Save workflow');

    // Verify state is 'review' again.
    $storage->resetCache([$sid]);
    $submission = $storage->load($sid);
    $element_data = $submission->getElementData('workflow');
    $this->assertSame('review', $element_data['workflow_state']);

    // =========================================================
    // PART 8: Reviewer approves (review → approved).
    // =========================================================
    // The reviewer sees the resubmission in 'review' state.
    // They can see the log from the submitter's resubmission
    // and select 'Approve' with final log messages.
    $this->drupalLogin($this->reviewerUser);
    $this->drupalGet($view_url);
    $this->assertSession()->statusCodeEquals(200);

    // Verify the resubmission log message is visible.
    $this->assertSession()->pageTextContains(
      'Section 3 completed',
    );

    // Approve with both log fields.
    $this->submitForm([
      'workflow[transition]' => 'approve',
      'workflow[log_public]' => 'Approved, well done!',
      'workflow[log_admin]' => 'All criteria met',
    ], 'Save workflow');

    // Verify the final state is 'approved'.
    $storage->resetCache([$sid]);
    $submission = $storage->load($sid);
    $element_data = $submission->getElementData('workflow');
    $this->assertSame(
      'approved',
      $element_data['workflow_state'],
    );

    // =========================================================
    // PART 9: Terminal state — no transitions available.
    // =========================================================
    // 'approved' has no outgoing transitions, so neither the
    // reviewer nor the submitter should see the reduced
    // workflow form. The "Save workflow" button should be
    // absent for both users.
    $this->drupalGet($view_url);
    $this->assertSession()->buttonNotExists('Save workflow');

    $this->drupalLogin($this->submitterUser);
    $this->drupalGet($view_url);
    $this->assertSession()->buttonNotExists('Save workflow');
  }

}
