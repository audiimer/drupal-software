# Usage

## Adding the element to a webform

1. Edit your webform and add a **Webform Workflows Element** element.
2. On the **General** tab, select the workflow you created.
3. Save the element.

The workflow element will now appear on the submission edit form.

## Configuring the element

### Workflow selection

On the element's **General** tab, select which workflow to use from the
dropdown. Only workflows of the "Webform workflow" type are available.

### Log settings

You can control which log messages are collected when a transition occurs:

- **Public log message** — visible to the submitter on the submission view
  page. Can be set to optional, required, or hidden.
- **Admin log message** — visible only to administrators. Can be set to
  optional, required, or hidden.

### Per-transition settings

Under "Webform workflows element settings" on the General tab, each transition
has additional options:

- **Enable/disable** the transition entirely.
- **Message if disabled by a conditional** — custom message shown to users when
  a conditional disables this transition.
- **Run on edit** — automatically trigger the transition whenever the
  submission is edited (optionally limited to the submission owner).

## Access controls

### Element-level access

On the element's **Access** tab, configure which roles or users can see and
use the workflow element:

- **Update** access determines who can use the workflow on existing
  submissions. This defaults to "authenticated user".
- **Create** access determines who can use the element on new submissions.
  By default, the element does not appear on the initial submission form.
- **View** access determines who can see the workflow state on a submission.

### Per-transition access

Scroll down on the **Access** tab to find access rules for each transition
(e.g. "Workflow transition: Approve"). You can restrict each transition to
specific roles, users, or permissions.

When a user has access to the element but not to a specific transition, that
transition will appear disabled in the form widget.

### State-based update access

You can override update access based on the current workflow state. For each
state, toggle "Override update access" and configure which roles/users/
permissions can edit the submission when it is in that state.

For example, to lock a submission while it is under review so that only
reviewers can edit:

1. Edit the workflow element and go to the **Access** tab.
2. Find the state (e.g. "Review") under the per-state overrides.
3. Check **Override update access**.
4. Under **Roles**, select only the reviewer role.

The submitter can still *view* the submission but gets a 403 when trying
to open the edit form. Once the reviewer rejects (returning the
submission to Draft), the submitter regains edit access.

!!! tip "Redirect after update"
    When state-based edit access is active, saving the edit form can
    redirect back to the same edit URL — which now returns 403 because
    the state changed. Enable **"Display confirmation when submission
    is updated"** in the webform's *Settings > Confirmation* page so
    the user lands on the confirmation page instead.

## Conditional logic

You can disable transitions based on form values — for example, prevent
approval if a required field is empty.

1. Go to the element's **Conditionals** tab.
2. Add a new conditional.
3. In the **State** dropdown, find the "Workflow transitions" group — each
   transition is listed for disabling.
4. Configure the condition and save.

You can provide a custom message explaining why a transition is disabled:

1. Go to the element's **General** tab.
2. Under "Webform workflows element settings", find the transition.
3. Enter a message in **"Message if disabled by a conditional"**.

Without a custom message, users see a generic message about the transition
being disabled by a conditional.

## Color configuration

Each workflow state can be assigned a color for visual identification in the
UI. Colors appear in the results list, the submission view page, and the
workflow summary.

1. Edit the webform element and go to the **General** tab.
2. Under "Webform workflows element settings", each state has a **Color**
   dropdown.
3. Select a color from the available options.

The available color options can be configured globally at
**Administration > Structure > Webforms > Configuration > Workflows**
(`/admin/structure/webform/config/workflows`).

## Submission view page

When viewing a submission, the module shows:

- The current workflow state (with color badge if configured).
- A workflow transition form (if the user has access to any transitions).
- The workflow transition log (if logging is enabled on the webform).

## Workflow summary

Each webform with a workflow element has a **Workflows** tab that summarizes:

- All workflow states and transitions.
- Access rules per transition.
- Email handlers configured for transitions.

It is recommended to install the
[Workflows Diagram](https://www.drupal.org/project/workflows_diagram) module
to display your workflow as a visual diagram on this page.

## Enabling logging

To track workflow transitions in the submission log:

1. Go to your webform's **Settings > Submissions** page.
2. Enable **Submission logging**.
3. Each transition will now be recorded with details about the state change,
   the user, and any log messages entered.
