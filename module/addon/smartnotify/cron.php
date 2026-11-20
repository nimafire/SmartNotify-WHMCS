<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__, 3);
require_once $root . '/init.php';
require_once __DIR__ . '/lib/Support.php';

use WHMCS\Database\Capsule;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$config = \SmartNotify\Support::config();
$settings = ['enabled' => \SmartNotify\Support::setting('enabled', !empty($config['enabled']) ? '1' : '0'), 'vapid_subject' => \SmartNotify\Support::setting('vapid_subject', $config['vapid_subject']), 'message_length' => (int) \SmartNotify\Support::setting('message_length', (string) $config['message_length'])];
if ($settings['enabled'] !== '1') exit("SmartNotify is disabled.\n");
if (!$config['vapid_public_key'] || !$config['vapid_private_key']) exit("VAPID keys are not configured in config.php.\n");
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) exit("Composer dependencies are missing. Run composer install in the SmartNotify directory.\n");
require_once $autoload;
$webPush = new WebPush(['VAPID' => ['subject' => $settings['vapid_subject'], 'publicKey' => $config['vapid_public_key'], 'privateKey' => $config['vapid_private_key']]], ['TTL' => 300, 'urgency' => 'normal', 'timeout' => 10]);
$events = Capsule::table('mod_sn_events')->where('status', 'pending')->orderBy('id')->limit(50)->get();
foreach ($events as $event) {
    $sent = 0;
    $failed = 0;
    $subscriptions = Capsule::table('mod_sn_subscriptions')->where('is_active', 1)->limit(1000)->get();
    $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $event->message_excerpt)));
    $excerpt = mb_substr($excerpt, 0, max(80, min(1000, $settings['message_length'])));
    $title = $event->event_type === 'ticket_open' ? 'New support ticket' : ($event->event_type === 'customer_reply' ? 'New customer reply' : ($event->event_type === 'admin_reply' ? 'New admin reply' : 'SmartNotify test'));
    $adminPath = trim(\SmartNotify\Support::setting('admin_path', 'admin'), '/');
    $ticketUrl = \SmartNotify\Support::whmcsRootUrl() . $adminPath . '/supporttickets.php?action=view&id=' . (int) $event->ticket_id;
    $payload = json_encode(['title' => 'WHMCS Support', 'body' => $title . "\n" . ($event->customer_name ?: 'Customer') . "\nTicket #" . (int) $event->ticket_id . "\n" . $excerpt, 'url' => $ticketUrl, 'tag' => 'sn-event-' . $event->id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach ($subscriptions as $row) {
        $alreadySent = Capsule::table('mod_sn_delivery_logs')->where('event_id', $event->id)->where('subscription_id', $row->id)->where('status', 'sent')->exists();
        if ($alreadySent) continue;
        try {
            $subscription = Subscription::create(['endpoint' => $row->endpoint, 'publicKey' => $row->public_key, 'authToken' => $row->auth_token]);
            $webPush->queueNotification($subscription, $payload);
        } catch (Throwable $e) {
            $failed++;
            Capsule::table('mod_sn_delivery_logs')->insert(['event_id' => $event->id, 'subscription_id' => $row->id, 'admin_id' => $row->admin_id, 'status' => 'failed', 'error_message' => mb_substr(get_class($e) . ': subscription setup failed', 0, 490), 'attempted_at' => date('Y-m-d H:i:s')]);
        }
    }
    foreach ($webPush->flush() as $report) {
        $endpoint = (string) $report->getRequest()->getUri();
        $row = $subscriptions->first(function ($item) use ($endpoint) { return hash_equals(hash('sha256', $item->endpoint), hash('sha256', $endpoint)); });
        if (!$row) continue;
        $success = $report->isSuccess();
        $sent += $success ? 1 : 0;
        $failed += $success ? 0 : 1;
        $reason = $success ? null : mb_substr((string) $report->getReason(), 0, 490);
        Capsule::table('mod_sn_delivery_logs')->insert(['event_id' => $event->id, 'subscription_id' => $row->id, 'admin_id' => $row->admin_id, 'status' => $success ? 'sent' : 'failed', 'response_code' => $report->getResponse() ? $report->getResponse()->getStatusCode() : null, 'error_message' => $reason, 'attempted_at' => date('Y-m-d H:i:s')]);
        if (!$success && $report->isSubscriptionExpired()) Capsule::table('mod_sn_subscriptions')->where('id', $row->id)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }
    $attempts = (int) $event->attempts + 1;
    $retry = $failed > 0 && $attempts < 3;
    Capsule::table('mod_sn_events')->where('id', $event->id)->update(['status' => $retry ? 'pending' : 'processed', 'attempts' => $attempts, 'processed_at' => $retry ? null : date('Y-m-d H:i:s')]);
    echo 'Event ' . $event->id . ': ' . $sent . ' sent, ' . $failed . ' failed, attempt ' . $attempts . ".\n";
}
