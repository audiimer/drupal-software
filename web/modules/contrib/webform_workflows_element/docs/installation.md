# Installation

## Install with Composer

```bash
composer require drupal/webform_workflows_element
```

## Enable the module

Enable the module via Drush or the admin UI:

```bash
drush en webform_workflows_element
```

## Create a workflow

1. Go to **Administration > Configuration > Workflow > Workflows**
   (`/admin/config/workflow/workflows`).
2. Click **Add workflow**.
3. Select the **Webform workflow** type.
4. Give your workflow a name (e.g. "Review process") and save.

## Add states and transitions

1. On the workflow edit page, add your **states** (e.g. "Submitted",
   "In review", "Approved", "Rejected").
2. Add **transitions** that define how submissions move between states
   (e.g. "Start review": Submitted → In review, "Approve": In review →
   Approved).
3. You can optionally set an **initial state** — this is the state assigned to
   new submissions. If not set, the first state in the list is used.

### Example

| State       | Transitions from this state          |
|-------------|--------------------------------------|
| Submitted   | "Start review" → In review           |
| In review   | "Approve" → Approved, "Reject" → Rejected |
| Approved    | *(none — final state)*               |
| Rejected    | "Re-submit" → Submitted              |

A user can only move a submission along the transitions you define — they
cannot skip states. This is the key difference from the legacy D7
webform_workflow module.
