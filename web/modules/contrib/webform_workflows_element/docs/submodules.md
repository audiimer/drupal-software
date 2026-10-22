# Submodules

The module ships with two optional submodules for integration with other
systems.

## Webform Workflows Element Views

**Machine name:** `webform_workflows_element_views`

**Dependencies:** `webform_workflows_element`, `webform_views`

This submodule provides a **Views filter plugin** that lets you filter webform
submissions by workflow state in any View.

### Setup

1. Install and enable the [Webform Views](https://www.drupal.org/project/webform_views)
   module.
2. Enable the **Webform Workflows Element Views** submodule:

    ```bash
    drush en webform_workflows_element_views
    ```

3. In your View, add the **Webform workflow state** filter.
4. Configure the filter to select the webform and workflow element to filter
   on.
5. Users can then filter submissions by selecting a state from a dropdown.

The filter queries the `webform_submission_data` table and supports the
color-coded state options if colors are configured on the element.

## Webform Workflows Element Maestro

**Machine name:** `webform_workflows_element_maestro`

**Dependencies:** `webform_workflows_element`, `maestro`

This submodule integrates with the
[Maestro](https://www.drupal.org/project/maestro) BPM (Business Process
Manager) module, providing two engine task plugins.

### Setup

1. Install and enable the [Maestro](https://www.drupal.org/project/maestro)
   module.
2. Enable the **Webform Workflows Element Maestro** submodule:

    ```bash
    drush en webform_workflows_element_maestro
    ```

### Task plugins

#### Maestro Transition Webform Workflow Task

This interactive task requires a user to perform a workflow transition on a
webform submission before the Maestro process can continue. It presents the
submission's workflow form to the assigned user.

#### Maestro Webform Workflow State If Task

This is a conditional (IF) task that checks the current workflow state of a
submission and branches the Maestro process accordingly. Use it to route the
process based on which state a submission is in.
