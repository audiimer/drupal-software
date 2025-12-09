---

# Acquia Flush Varnish Module

The **Acquia Flush Varnish** module provides a Drupal admin-side interface to manually purge the Acquia Cloud Varnish cache using the Acquia Cloud API. It allows you to flush the Varnish cache at the domain level, saving valuable developer time during implementation.

## Summary

This module simplifies the process of clearing the Varnish cache by providing an intuitive interface within the Drupal admin. Developers can trigger cache purges directly from the Acquia Cloud API, streamlining the workflow.

## Requirements

No specific requirements are needed for this module. It works seamlessly with your existing Drupal installation.

## Installation

1. Install the module as you would with any other Drupal module.
2. Enable the **Acquia Flush Varnish** module in the Drupal admin.

## Configuration

To configure the module, follow these steps:

1. Add your Acquia Cloud API credentials to the Acquia server via SSH.
2. **Note**: It is not recommended to store the API key and secret in the codebase or database. Instead, use the `secrets.settings.php` method to set up the API key and secret in Acquia Cloud or Site Factory (Acquia's recommended approach).
3. Reference: [Acquia Document Link] (https://docs.acquia.com/secrets#section-secretssettingsphp-file)

### Setting Up `secrets.settings.php`

Create a `secrets.settings.php` file with the following structure in the Acquia server's nobackup location. Then include it in `settings.php` (for Acquia Cloud) or the factory hook (for Acquia Site Factory):

```php
<?php
/**
 * @file
 * File to store Acquia API details.
 */
$config['acquia_flush_varnish.settings']['acquiacloud_apikey'] = 'xxxxxx';
$config['acquia_flush_varnish.settings']['acquiacloud_secret'] = 'xxxxxxxx-yyyyy';
```

## Frequently Asked Questions (FAQ)

**Q: How do I obtain Acquia Cloud API credentials?**

A: Follow these steps:
1. Log in to [Acquia Accounts](https://accounts.acquia.com/sign-in) using your Acquia account credentials.
2. Navigate to **Accounts > API Tokens**.
3. Create a new API key and secret in the API Tokens tab.

## Contact

Current maintainers:
- Buvaneswaran (buvan) - [Drupal.org Profile](http://www.drupal.org/u/buvanesh.chandrasekaran)
