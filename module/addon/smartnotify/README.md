# SmartNotify 2.0.0

Simple browser push notifications for WHMCS 8.13+ and PHP 8.1+.

## Install

1. Upload `smartnotify` to `modules/addons/`.
2. Run `composer install --no-dev --optimize-autoloader` inside the module directory using the PHP 8.1 CLI.
3. Copy `config.php.example` to `config.php`.
4. Generate VAPID keys with `php generate-vapid.php` and add them to `config.php`.
5. Set `vapid_subject` to a valid `mailto:` address and set `admin_path` to the actual WHMCS admin directory.
6. Protect `config.php` and keep its permissions restricted to the PHP account.
7. Activate SmartNotify under WHMCS Addon Modules and grant access to the intended administrator role. Activation installs the WHMCS event hooks automatically.
8. Open SmartNotify and click Enable Notifications.

## Requirements

- HTTPS on the WHMCS website.
- PHP 8.1+ with OpenSSL, cURL, JSON and mbstring.
- Browser support for Service Worker, Notifications and Push API.
- Minishlink Web Push installed through Composer.

## Storage

The current browser subscription and event preferences are stored as protected JSON files in `storage/`. No SmartNotify database tables are created.

## Events

TicketOpen, TicketUserReply, TicketAdminReply, InvoiceCreated, InvoicePaid, ClientAdd and AfterShoppingCartCheckout.

## Notes

The addon sends push notifications to the single browser subscription currently connected to this installation. A new browser connection replaces the previous subscription. Browser permission is requested only after the administrator clicks Enable Notifications.
