# Filtering results

The module provides multiple ways to filter webform submissions by their
workflow state.

## Built-in results filtering

The module adds workflow state filters to the webform's built-in results page
at **Results > Submissions** without requiring any additional modules.

A state filter dropdown appears for each workflow element on the webform. The
filter uses query parameters in the format:

```
?workflow-ELEMENT_ID=STATE_ID
```

For example, `?workflow-workflow=approved` filters to submissions in the
"approved" state for the element with machine name `workflow`.

## Webform Better Results integration

If the [Webform Better Results](https://www.drupal.org/project/webform_better_results)
module is installed, the workflow state filter is also added to its enhanced
results interface. The module integrates via:

- `hook_webform_better_results_form_alter()` — adds the filter form element.
- `hook_webform_better_results_submit_alter()` — processes the filter
  selection.
- `hook_webform_better_results_query_alter()` — applies the SQL filtering on
  the `webform_submission_data` table.

The same query parameter format (`workflow-ELEMENT_ID=STATE_ID`) is used.

## Views integration

For more advanced filtering, the **webform_workflows_element_views** submodule
provides a Views filter plugin. See the [Submodules](submodules.md) page for
details.
