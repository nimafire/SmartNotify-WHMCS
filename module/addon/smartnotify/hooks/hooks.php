<?php
use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/lib/Support.php';

function smartnotify_ticket_event(string $type, array $vars): void
{
    try {
        $ticketId = (int) ($vars['ticketid'] ?? $vars['ticket_id'] ?? 0);
        if ($ticketId < 1) return;
        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        if (!$ticket) return;
        $messageId = (int) Capsule::table('tblticketreplies')->where('tid', $ticketId)->max('id');
        if ($messageId < 1) $messageId = $ticketId;
        $message = trim(strip_tags(html_entity_decode((string) ($vars['message'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $message = preg_replace('/\s+/u', ' ', $message);
        $customer = trim((string) ($ticket->name ?? ''));
        \SmartNotify\Support::queueEvent(['event_type' => $type, 'ticket_id' => $ticketId, 'customer_id' => (int) ($ticket->userid ?? 0), 'subject' => (string) ($ticket->title ?? ''), 'message_excerpt' => mb_substr($message, 0, 2000), 'customer_name' => $customer, 'message_id' => $messageId]);
    } catch (Throwable $e) {
        logActivity('SmartNotify hook error: ' . $e->getMessage());
    }
}

add_hook('TicketOpen', 1, function ($vars) { smartnotify_ticket_event('ticket_open', (array) $vars); });
add_hook('TicketUserReply', 1, function ($vars) { smartnotify_ticket_event('customer_reply', (array) $vars); });
add_hook('TicketAdminReply', 1, function ($vars) { smartnotify_ticket_event('admin_reply', (array) $vars); });
