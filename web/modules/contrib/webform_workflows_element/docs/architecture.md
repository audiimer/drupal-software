# Architecture

## Module structure

The module is organized into a core module and two optional submodules:

```
webform_workflows_element/
├── src/
│   ├── Hook/
│   │   ├── EntityTypeHooks.php                  # Entity type alter
│   │   ├── FormHooks.php                        # Form alter hooks
│   │   ├── ResultsFilterHooks.php               # Results filter hooks
│   │   ├── ThemeHooks.php                       # Theme hooks
│   │   ├── TokenHooks.php                       # Token hooks
│   │   └── WebformWorkflowsElementHooks.php     # Core lifecycle hooks
│   ├── Service/
│   │   └── WebformWorkflowsManager.php          # Core manager service
│   ├── Event/
│   │   └── WebformSubmissionWorkflowTransitionEvent.php
│   ├── EventSubscriber/
│   │   └── TransitionEventSubscriber.php        # Logs transitions
│   ├── Element/
│   │   └── WebformWorkflowsElement.php          # Form API element
│   ├── Plugin/
│   │   ├── WebformElement/
│   │   │   └── WebformWorkflowsElement.php      # Webform element plugin
│   │   ├── WebformHandler/
│   │   │   └── StateChangeEmailWebformHandler.php
│   │   ├── WorkflowType/
│   │   │   └── WebformWorkflowsElement.php      # Workflow type plugin
│   │   └── Action/
│   │       └── WebformWorkflowTransition.php    # Action plugin
│   ├── Controller/
│   │   └── WorkflowsSummaryController.php
│   ├── Form/
│   │   ├── WebformWorkflowsElementConfigureForm.php
│   │   ├── WebformWorkflowTransitionConfirmForm.php
│   │   └── AdminConfig/
│   │       └── WebformAdminConfigWorkflowsForm.php
│   └── WebformSubmissionWorkflowListBuilder.php
├── modules/
│   ├── webform_workflows_element_maestro/
│   │   └── src/Hook/MaestroHooks.php
│   └── webform_workflows_element_views/
│       └── src/Hook/ViewsHooks.php
├── config/
│   ├── install/
│   │   └── webform_workflows_element.settings.yml
│   └── schema/
│       ├── webform_workflows_element.schema.yml
│       ├── webform_workflows_element.settings.schema.yml
│       └── webform_workflows_element.plugin.handler.schema.yml
├── webform_workflows_element.module             # LegacyHook stubs (D10)
├── webform_workflows_element.api.php            # Alter hook docs
├── webform_workflows_element.routing.yml
└── webform_workflows_element.services.yml
```

## OOP hook classes

All hooks use the `#[Hook]` attribute system (Drupal 11+). For Drupal 10
backward compatibility, the `.module` file contains `#[LegacyHook]` stubs that
delegate to the OOP classes via `\Drupal::service()`.

The hook logic lives in six classes under `src/Hook/`:

| Class | Hooks |
|-------|-------|
| `WebformWorkflowsElementHooks` | `webform_submission_view`, `webform_element_access`, `webform_submission_presave`, `webform_submission_access`, `entity_operation_alter` |
| `FormHooks` | `form_alter`, `webform_element_webform_workflows_element_alter`, `webform_submission_form_alter` |
| `ResultsFilterHooks` | `form_webform_submission_filter_form_alter`, `webform_better_results_query_alter`, `webform_better_results_form_alter`, `webform_better_results_submit_alter` |
| `TokenHooks` | `token_info`, `tokens` |
| `ThemeHooks` | `theme` |
| `EntityTypeHooks` | `entity_type_alter` |

The submodules follow the same pattern:

| Class | Hooks |
|-------|-------|
| `MaestroHooks` | `task_console_interactive_link_alter` |
| `ViewsHooks` | `views_data` |

## Service wiring

The module defines services in `webform_workflows_element.services.yml`:

- **`webform_workflows_element.manager`** — the central
  `WebformWorkflowsManager` service. It is injected with the logger factory,
  module handler, webform element manager, webform access rules manager, event
  dispatcher, entity type manager, current user, route match, config factory,
  date formatter, renderer, and the optional submission log manager.

- **`webform_workflows_element.transition_events_subscriber`** — the
  `TransitionEventSubscriber` that listens for transition events and writes log
  entries via the manager.

The OOP hook classes are auto-registered as autowired services by Drupal 11's
`HookCollectorPass`. For Drupal 10 compatibility they are also explicitly
registered in `services.yml`.

## Plugin system

The module provides several Drupal plugins:

### Workflow type plugin

`Plugin\WorkflowType\WebformWorkflowsElement` extends `WorkflowTypeBase` and
defines the "Webform workflow" type available in the core Workflows UI. It adds
support for an initial state setting.

### Webform element plugin

`Plugin\WebformElement\WebformWorkflowsElement` extends `WebformCompositeBase`
and registers the element with the Webform module. It handles:

- Element configuration form (workflow selection, transitions, colors, access).
- Export headers and records for the composite sub-elements.
- Default values and element properties.

### Form API element

`Element\WebformWorkflowsElement` extends `WebformCompositeBase` and renders
the actual form widget. The composite contains sub-elements:

- `workflow_state` — current state (hidden).
- `workflow_state_previous` — previous state (hidden).
- `workflow_state_label` — state label display.
- `transition` — select widget with available transitions.
- `changed_user` — user ID (hidden).
- `changed_timestamp` — timestamp (hidden).
- `log_public` — public log message textarea.
- `log_admin` — admin log message textarea.

### Email handler plugin

`Plugin\WebformHandler\StateChangeEmailWebformHandler` extends the core
`EmailWebformHandler` and adds transition-based triggers. Each handler instance
can be configured to fire on one or more specific transitions.

### Action plugin

`Plugin\Action\WebformWorkflowTransition` provides a configurable action for
bulk operations. It allows executing a specific transition on selected
submissions.

## Event system

When `runTransition()` is called on the manager service, it dispatches a
`WebformSubmissionWorkflowTransitionEvent` via Symfony's event dispatcher.
The built-in `TransitionEventSubscriber` listens for this event and calls
`logTransition()` to write to the webform submission log.

Custom modules can subscribe to the same event to react to transitions — see
the [Developer API](api.md) page for details.

## Custom list builder

The module replaces the default webform submission list builder with
`WebformSubmissionWorkflowListBuilder`, which extends
`WebformSubmissionListBuilder` to support filtering submissions by workflow
state on the results page.

## Configuration

Module-wide settings are stored in `webform_workflows_element.settings`:

- `ui.color_options` — list of available colors for state badges
  (configurable at `/admin/structure/webform/config/workflows`).
- `mail.default_body_text` — default plain-text email body template.
- `mail.default_body_html` — default HTML email body template.

Per-element configuration (workflow selection, transition settings, access
rules, colors) is stored as part of the webform's element YAML.
