# Email handlers & tokens

## Adding a workflow transition email handler

The module provides a specialized email handler that sends emails when specific
workflow transitions occur.

1. Go to your webform's **Settings > Emails / Handlers** page.
2. Click **Add handler** and select **Workflow transition email**.
3. Configure the email settings (recipients, subject, body) as you would with
   a standard email handler.
4. On the **Advanced** tab under **Additional settings**, select which
   transitions should trigger this email. If the webform has multiple workflow
   elements, the element name appears in brackets after each transition.

You can add multiple email handlers to send different emails for different
transitions.

## Workflow tokens

The module provides four tokens for use in email bodies, subjects, and
anywhere Drupal tokens are supported. All tokens follow the format:

```
[webform_workflow:TOKEN_NAME:ELEMENT_ID:TRANSITION_ID]
```

Replace `ELEMENT_ID` with the machine name of your workflow element and
`TRANSITION_ID` with the machine name of the transition.

### Available tokens

| Token | Description |
|-------|-------------|
| `transition-url` | URL to the submission page with query parameters to trigger the transition. The user must be logged in. |
| `transition-link` | HTML link using the transition label as link text. The user must be logged in. |
| `transition-url-secure-token` | URL with a secure token appended — the user does **not** need to be logged in. The webform must be configured to allow updates via secure token. |
| `transition-link-secure-token` | HTML link with secure token — same requirements as above. |

### Examples

```
[webform_workflow:transition-url:workflow:approve]
[webform_workflow:transition-link:workflow:approve]
[webform_workflow:transition-url-secure-token:workflow:approve]
[webform_workflow:transition-link-secure-token:workflow:approve]
```

In these examples, `workflow` is the element machine name and `approve` is the
transition machine name.

### Standard webform tokens

You can also use standard webform submission tokens to include workflow data in
emails:

- `[webform_submission:values:ELEMENT_ID:workflow_state_label]` — the current
  state label.
- `[webform_submission:values:ELEMENT_ID:log_public]` — the public log message
  entered during the transition.

### Secure token requirements

For `transition-url-secure-token` and `transition-link-secure-token` to work,
the webform must be configured to allow users to update submissions using a
secure token. This is configured in the webform's **Settings > Submissions**
section.
