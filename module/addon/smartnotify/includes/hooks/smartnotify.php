<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once dirname(__DIR__, 2) . '/modules/addons/smartnotify/lib/SmartNotify.php';

function smartnotify_push(string $event, string $title, string $body, string $url = ''): void
{
    try {
        $service = new SmartNotify();
        $service->notifyEvent($event, $title, $body, $url);
    } catch (Throwable $e) {
        logActivity('SmartNotify: ' . $e->getMessage());
    }
}

add_hook('TicketOpen', 1, function ($vars) {
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $subject = trim((string) ($vars['subject'] ?? 'New support ticket'));
    smartnotify_push('ticket_open', 'New support ticket', $subject, smartnotify_ticket_url($ticketId));
});

add_hook('TicketUserReply', 1, function ($vars) {
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $subject = trim((string) ($vars['subject'] ?? 'Customer replied to a ticket'));
    smartnotify_push('ticket_reply', 'Customer ticket reply', $subject, smartnotify_ticket_url($ticketId));
});

add_hook('TicketAdminReply', 1, function ($vars) {
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $subject = trim((string) ($vars['subject'] ?? 'Admin replied to a ticket'));
    smartnotify_push('admin_reply', 'Admin ticket reply', $subject, smartnotify_ticket_url($ticketId));
});

add_hook('InvoiceCreated', 1, function ($vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    smartnotify_push('invoice_created', 'New invoice', 'Invoice #' . $invoiceId . ' has been created.', smartnotify_invoice_url($invoiceId));
});

add_hook('InvoicePaid', 1, function ($vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    smartnotify_push('invoice_paid', 'Invoice paid', 'Invoice #' . $invoiceId . ' has been paid.', smartnotify_invoice_url($invoiceId));
});

add_hook('ClientAdd', 1, function ($vars) {
    $name = trim((string) (($vars['firstname'] ?? '') . ' ' . ($vars['lastname'] ?? '')));
    smartnotify_push('client_add', 'New client registration', $name !== '' ? $name . ' registered.' : 'A new client registered.');
});

add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $orderId = (int) ($vars['OrderID'] ?? $vars['orderid'] ?? 0);
    smartnotify_push('order_created', 'New order', 'A new order has been placed.' . ($orderId ? ' Order #' . $orderId . '.' : ''));
});

function smartnotify_ticket_url(int $ticketId): string
{
    $service = new SmartNotify();
    $config = $service->getConfig();
    $admin = trim((string) ($config['admin_path'] ?? 'admin'), '/');
    return $service->systemUrl() . $admin . '/supporttickets.php?action=view&id=' . $ticketId;
}

function smartnotify_invoice_url(int $invoiceId): string
{
    $service = new SmartNotify();
    $config = $service->getConfig();
    $admin = trim((string) ($config['admin_path'] ?? 'admin'), '/');
    return $service->systemUrl() . $admin . '/invoices.php?action=edit&id=' . $invoiceId;
}
