# SmartNotify for WHMCS

**Browser Push Notifications for WHMCS 8.13+**

SmartNotify is a lightweight WHMCS addon that delivers real-time browser push notifications for important WHMCS events.

It uses the Web Push API, Service Workers, and VAPID authentication to notify administrators even when the WHMCS browser tab is not active.

## Features

- New support ticket notifications
- Customer ticket reply notifications
- Admin ticket reply notifications
- New invoice notifications
- Invoice payment notifications
- New client registration notifications
- New order notifications
- Enable and disable browser notifications
- Send test notifications
- Select which events trigger notifications
- JSON-based storage
- No custom database tables
- Automatic WHMCS hook installation and removal

## Requirements

- WHMCS 8.13 or later
- PHP 8.1 or later
- HTTPS-enabled WHMCS installation
- PHP extensions: OpenSSL, cURL, JSON, and mbstring
- Composer
- A browser supporting Service Workers, Notifications, and the Push API

## Installation

### 1. Upload the addon

Upload the `smartnotify` directory to:

```text
WHMCS_ROOT/modules/addons/smartnotify
```

### 2. Install dependencies

Run Composer inside the addon directory:

```bash
cd /path/to/whmcs/modules/addons/smartnotify
composer install --no-dev --optimize-autoloader
```

### 3. Create the configuration file

Copy the example configuration:

```bash
cp config.php.example config.php
```

Edit `config.php`:

```php
<?php

return [
    'vapid_subject' => 'mailto:admin@example.com',
    'vapid_public_key' => '',
    'vapid_private_key' => '',
    'message_length' => 180,
    'admin_path' => 'admin'
];
```

Configure the VAPID subject, public key, private key, and WHMCS admin directory.

### 4. Generate VAPID keys

If you do not already have a VAPID key pair, run:

```bash
php generate-vapid.php
```

Add the generated keys to `config.php`.

**Important:** Keep your private VAPID key secret. Never commit `config.php` or private keys to Git. Preserve your existing VAPID keys when upgrading.

### 5. Activate the addon

1. Log in to the WHMCS admin area.
2. Navigate to **Configuration → System Settings → Addon Modules**.
3. Find SmartNotify and click **Activate**.
4. Grant access to the required administrator roles.
5. Open the SmartNotify addon page.

The activation process installs the WHMCS event hook automatically.

## Enable Browser Notifications

1. Open the SmartNotify page in WHMCS Admin.
2. Click **Enable Notifications**.
3. Allow notifications when prompted by your browser.
4. SmartNotify registers its Service Worker and saves the browser push subscription.
5. Confirm that the status changes to **Connected**.

## Test Notifications

Click **Send Test** on the SmartNotify page.

You should receive a test browser notification:

```text
SmartNotify Test
Browser push notifications are working.
```

Actual notification display depends on browser and operating system notification settings.

## Configure Events

SmartNotify supports the following events:

| Event | Description | Default |
|---|---|---|
| `ticket_open` | New support ticket | Enabled |
| `ticket_reply` | Customer ticket reply | Enabled |
| `admin_reply` | Admin ticket reply | Disabled |
| `invoice_created` | New invoice | Disabled |
| `invoice_paid` | Invoice paid | Enabled |
| `client_add` | New client registration | Disabled |
| `order_created` | New order | Disabled |

Select the events you want to receive and click **Save Events**.

## Disable Notifications

Click **Disable Notifications** on the SmartNotify page.

This removes the server-side subscription and unsubscribes the browser from Push notifications.

## Storage

SmartNotify uses JSON files instead of custom database tables.

```text
modules/addons/smartnotify/
├── assets/
├── includes/
├── lib/
├── storage/
├── config.php
├── smartnotify.php
└── sw.php
```

The following files contain persistent configuration and subscription data:

- `config.php`
- `storage/settings.json`
- `storage/subscription.json`

Back them up before upgrades or migrations.

The current version supports **one active browser subscription** per WHMCS installation. Connecting another browser replaces the previously stored subscription.

## Security Notes

- Use HTTPS for your WHMCS installation.
- Keep `config.php` and the VAPID private key confidential.
- Do not commit `config.php`, `vendor/`, or runtime storage files.
- Restrict direct web access to the `storage` directory.
- For Nginx, add an access restriction for the storage path:

```nginx
location ^~ /modules/addons/smartnotify/storage/ {
    deny all;
    return 404;
}
```

## Upgrade Notes

When upgrading SmartNotify:

1. Back up the existing addon directory.
2. Preserve your existing `config.php`.
3. Preserve `storage/settings.json` and `storage/subscription.json`.
4. Replace the addon source files.
5. Run Composer if dependencies have changed.
6. Open the SmartNotify page and test notifications.

Do not generate new VAPID keys during an upgrade. Changing the key pair can require the browser to subscribe again.

## Deactivation

Deactivating the addon removes the installed WHMCS hook file.

Stored settings and subscription data are preserved.

## License

No license has been specified for this repository yet. Add a license file before distributing the project under an open-source license.

## Version

**2.0.0**
