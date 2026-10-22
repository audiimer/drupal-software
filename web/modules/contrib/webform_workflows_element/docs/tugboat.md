# Tugboat preview

Every merge request on the Drupal.org issue queue gets a
[Tugboat](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/tugboat)
live preview — a throwaway Drupal site you can click around in without
installing anything locally.

## What's deployed

The preview installs Drupal with the **standard** profile and enables:

- **webform_workflows_element** (the module under review)
- **webform_node** — provides the "Webform" content type used for the
  demo node
- **webform_ui** — so you can inspect and edit the demo webform
- **webform_submission_log** — so transition history is visible
- **admin_toolbar** — for easier navigation

On top of that, the Tugboat build imports:

- A **Lifecycle Workflow** with three states and three transitions
- A **Lifecycle Form** webform at `/lifecycle`
- A **webform node** promoted to the front page that embeds the Lifecycle
  Form with credentials and a quick-start guide
- Two custom roles (**Submitter** and **Reviewer**)
- Four demo user accounts (see below)

## Demo workflow

The Lifecycle Workflow has the following states and transitions:

| State    | Color  |
|----------|--------|
| Draft    | Blue   |
| Review   | Orange |
| Approved | Green  |

| Transition        | From   | To       | Allowed role |
|-------------------|--------|----------|--------------|
| Submit for review | Draft  | Review   | Submitter    |
| Approve           | Review | Approved | Reviewer     |
| Reject            | Review | Draft    | Reviewer     |

### State-based edit access

The demo also showcases the **per-state edit access override** feature.
While a submission is in the *Review* state, only users with the
**Reviewer** role can edit it — the submitter can still view their
submission but gets a 403 if they try to open the edit form. This
prevents the submitter from modifying the content while it is under
review.

Once the reviewer rejects the submission (back to *Draft*), the
submitter regains edit access and can revise and resubmit.

The relevant element properties are:

- `#allow_restricting_editing_by_state: true`
- `#access_update_at_state_review_override: true`
- `#access_update_at_state_review_roles: [reviewer]`

## Demo users

All passwords match the username.

| Username      | Password      | Role      | What they can do                            |
|---------------|---------------|-----------|---------------------------------------------|
| **admin**     | admin         | Admin     | Full site administration                    |
| **submitter** | submitter     | Submitter | Create submissions, submit for review       |
| **reviewer**  | reviewer      | Reviewer  | View any submission, approve or reject      |
| **user**      | user          | *(none)*  | Basic authenticated user — gets 403 on form |

!!! note "Workflow element only appears on the edit form"
    By default the workflow element is **hidden on the create form** and
    only shows when **editing** an existing submission. This is the
    standard behavior: you create a submission first (it starts at the
    initial state, *Draft*), then edit it to perform workflow transitions.

## Walkthrough

### 1. Create a submission

1. Log in as **submitter** / `submitter`.
2. Go to the front page — the webform node with the Lifecycle Form is
   right there (or use the **Lifecycle Form** toolbar shortcut).
3. Fill in the **Full name** field and submit the form.
4. The submission is now in the *Draft* state.

### 2. Submit for review

1. Still logged in as **submitter**, go back to `/lifecycle`. You should
   see a *"View your previous submissions"* link above the form.
2. Click it, then click **Edit** on your submission.
3. The **Workflow** element now appears — select **Submit for review**
   and save.
4. The workflow state changes from *Draft* (blue) to *Review* (orange).

### 3. Approve or reject

1. Log out and log in as **reviewer** / `reviewer`.
2. Use the **Submissions** toolbar shortcut (or navigate to
   *Structure > Webforms > Lifecycle Form > Results > Submissions*) to
   open the submissions list.
3. Click **Edit** on the submission that is in *Review* state.
4. In the **Workflow** element, select **Approve** or **Reject** and save.
5. **Approve** moves the state to *Approved* (green); **Reject** sends it
   back to *Draft* (blue) so the submitter can revise and resubmit.

### 4. Verify access control

1. Log out and log in as **user** / `user`.
2. Visit the webform node from the front page — the form should not
   appear because the *user* account has no workflow role granting
   access to it.

!!! tip "Toolbar shortcuts"
    Two shortcuts are added to the default toolbar shortcut set:
    **Lifecycle Form** (the webform node) and **Submissions** (the
    results page). Any user with toolbar access can use them.
