<?php

/**
 * @file
 * Tugboat demo setup for webform_workflows_element.
 *
 * Creates a front-page webform node referencing the lifecycle form,
 * and a toolbar shortcut to it.
 * Called via drush php:script.
 */

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\shortcut\Entity\Shortcut;

// Build the submissions list URL from the route so it stays correct
// even if admin paths change.
$submissions_url = Url::fromRoute(
  'entity.webform.results_submissions',
  ['webform' => 'lifecycle_form'],
)->toString();

// Create a webform node (provided by webform_node) that embeds the
// lifecycle form. The front page is set to /lifecycle in config.yml
// via drush config:set, so visitors land directly on this node.
$body = '<p>This is a live preview of the '
  . '<a href="https://www.drupal.org/project/webform_workflows_element">'
  . 'Webform Workflows Element</a> module.</p>'
  . '<p>The workflow element only appears when <strong>editing</strong> '
  . 'an existing submission — not on the initial create form. '
  . 'After submitting, click <em>View your previous submissions</em> '
  . 'to find your submission and edit it.</p>'
  . '<p>Reviewers: open the <a href="' . $submissions_url
  . '">submissions list</a> to find '
  . 'submissions waiting for review.</p>'
  . '<h2>Demo accounts</h2>'
  . '<table>'
  . '<tr><th>Username</th><th>Password</th><th>Role</th></tr>'
  . '<tr><td>admin</td><td>admin</td><td>Administrator</td></tr>'
  . '<tr><td>submitter</td><td>submitter</td><td>Submitter</td></tr>'
  . '<tr><td>reviewer</td><td>reviewer</td><td>Reviewer</td></tr>'
  . '<tr><td>user</td><td>user</td>'
  . '<td>Authenticated (no workflow role)</td></tr>'
  . '</table>';

$node = Node::create([
  'type' => 'webform',
  'title' => 'Lifecycle Form — Workflow Demo',
  'body' => [
    'value' => $body,
    'format' => 'full_html',
  ],
  'webform' => [
    'target_id' => 'lifecycle_form',
    'status' => 'open',
  ],
  'path' => ['alias' => '/lifecycle'],
  'promote' => TRUE,
  'uid' => 1,
]);
$node->save();

// Toolbar shortcut pointing to the webform node via its alias.
Shortcut::create([
  'shortcut_set' => 'default',
  'title' => 'Lifecycle Form',
  'link' => ['uri' => 'internal:/lifecycle'],
  'weight' => 0,
])->save();

// Toolbar shortcut to the submissions list so reviewers can quickly
// find submissions waiting for review.
Shortcut::create([
  'shortcut_set' => 'default',
  'title' => 'Submissions',
  'link' => ['uri' => 'internal:' . $submissions_url],
  'weight' => 1,
])->save();
