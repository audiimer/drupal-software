---
hide:
  - toc
---

# Webform Workflows Element

The [Webform Workflows Element](https://www.drupal.org/project/webform_workflows_element)
module provides a webform element that integrates with Drupal's core
[Workflows](https://www.drupal.org/docs/8/core/modules/workflows) module,
allowing submissions to be transitioned through defined workflow states.

You define a workflow (with the "Webform workflow" type), add it as an element
to a webform, and users with the right access can transition each submission
through the workflow. You can then track submissions, trigger email handlers,
and apply conditional logic based on workflow progress.

## Features

- Workflow states and transitions powered by core Workflows
- Role-based and permission-based access control per element and per transition
- Conditional logic to disable transitions based on form values
- Email notifications per transition, with workflow-specific tokens
- Public (submitter-facing) and admin-only log messages on transitions
- Transition logging to the webform submission log
- Color-coded workflow states in the UI
- Results filtering by workflow state (built-in and via webform_better_results)
- Views integration via the **webform_workflows_element_views** submodule
- Maestro BPM integration via the **webform_workflows_element_maestro** submodule
- Secure token URLs for performing transitions without logging in
- Workflow summary page per webform

## Requirements

- Drupal 10.3 or 11
- [Workflows](https://www.drupal.org/docs/8/core/modules/workflows) (core)
- [Webform](https://www.drupal.org/project/webform)

## Optional integrations

- [Webform Views](https://www.drupal.org/project/webform_views) — for the
  Views filter submodule
- [Maestro](https://www.drupal.org/project/maestro) — for the Maestro
  integration submodule
- [Webform Better Results](https://www.drupal.org/project/webform_better_results) —
  enhanced results filtering
- [Webform Group](https://www.drupal.org/project/webform_group) — group-based
  access control for transitions
- [Workflows Diagram](https://www.drupal.org/project/workflows_diagram) —
  visual diagram of your workflow on the summary page
