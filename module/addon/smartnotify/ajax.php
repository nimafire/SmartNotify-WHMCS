<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ini_set('display_errors', '0');

function sn_response(int $status, array $data): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $root = dirname(__DIR__, 3);
    if (!is_file($root . '/init.php')) sn_response(500, ['ok' => false, 'message' => 'WHMCS bootstrap was not found.']);
    require_once $root . '/init.php';
    require_once __DIR__ . '/lib/Support.php';
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $adminId = (int) ($_SESSION['adminid'] ?? 0);
    if ($adminId < 1) sn_response(401, ['ok' => false, 'message' => 'Administrator session expired. Please sign in again.']);
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $validToken = false;
    if ($token !== '' && function_exists('check_token')) {
        $_REQUEST['token'] = $token;
        $validToken = (bool) check_token('WHMCS.admin.default');
    }
    if (!$validToken && $token !== '') {
        $sessionToken = (string) ($_SESSION['token'] ?? '');
        $validToken = $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
    if (!$validToken) sn_response(403, ['ok' => false, 'message' => 'Security token is invalid. Refresh the page and try again.']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sn_response(405, ['ok' => false, 'message' => 'POST requests only.']);
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 16384) sn_response(413, ['ok' => false, 'message' => 'Request is too large.']);
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') sn_response(415, ['ok' => false, 'message' => 'Content-Type must be application/json.']);
    $raw = file_get_contents('php://input');
    if (strlen((string) $raw) > 16384) sn_response(413, ['ok' => false, 'message' => 'Request is too large.']);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) sn_response(400, ['ok' => false, 'message' => 'Request body is not valid JSON.']);
    $action = (string) ($data['action'] ?? '');
    $config = \SmartNotify\Support::config();
    if ($action === 'subscribe') {
        $sub = $data['subscription'] ?? null;
        if (!is_array($sub)) sn_response(422, ['ok' => false, 'message' => 'Browser subscription data is invalid: subscription object is missing.']);
        $endpoint = filter_var($sub['endpoint'] ?? '', FILTER_VALIDATE_URL);
        $keys = $sub['keys'] ?? [];
        $p256dh = is_array($keys) ? (string) ($keys['p256dh'] ?? '') : '';
        $auth = is_array($keys) ? (string) ($keys['auth'] ?? '') : '';
        if (!$endpoint || !in_array(strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)), ['https'], true) || strlen($endpoint) > 4096) sn_response(422, ['ok' => false, 'message' => 'Browser subscription endpoint is invalid.']);
        if (!preg_match('/^[A-Za-z0-9_-]{80,120}$/', $p256dh) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $auth)) sn_response(422, ['ok' => false, 'message' => 'Browser subscription keys are invalid or incomplete.']);
        $hash = hash('sha256', $endpoint);
        $existing = \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->where('endpoint_hash', $hash)->first();
        $values = ['admin_id' => $adminId, 'endpoint' => $endpoint, 'public_key' => $p256dh, 'auth_token' => $auth, 'is_active' => 1, 'last_seen_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->where('id', $existing->id)->update($values);
        } else {
            $values['created_at'] = date('Y-m-d H:i:s');
            \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->insert(array_merge(['endpoint_hash' => $hash], $values));
        }
        sn_response(200, ['ok' => true, 'message' => 'Browser connected successfully.', 'status' => 'connected']);
    }
    if ($action === 'disconnect') {
        $endpoint = (string) ($data['endpoint'] ?? '');
        if ($endpoint === '') sn_response(422, ['ok' => false, 'message' => 'Endpoint is required.']);
        \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->where('endpoint_hash', hash('sha256', $endpoint))->where('admin_id', $adminId)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        sn_response(200, ['ok' => true, 'message' => 'This browser was disconnected.']);
    }
    if ($action === 'subscription_action') {
        $id = (int) ($data['id'] ?? 0);
        $operation = (string) ($data['operation'] ?? '');
        $query = \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->where('id', $id);
        if ($operation === 'delete') $query->delete();
        elseif (in_array($operation, ['activate', 'deactivate'], true)) $query->update(['is_active' => $operation === 'activate' ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
        else sn_response(422, ['ok' => false, 'message' => 'Unknown subscription operation.']);
        sn_response(200, ['ok' => true, 'message' => 'Browser record updated.']);
    }
    if ($action === 'settings') {
        $subject = trim((string) ($data['vapid_subject'] ?? ''));
        $messageLength = max(80, min(1000, (int) ($data['message_length'] ?? 180)));
        if (!preg_match('/^(mailto:[^\s@]+@[^\s@]+|https:\/\/.+)$/i', $subject)) sn_response(422, ['ok' => false, 'message' => 'VAPID Subject must be a mailto: or HTTPS URL.']);
        \SmartNotify\Support::setSetting('vapid_subject', $subject);
        \SmartNotify\Support::setSetting('message_length', (string) $messageLength);
        \SmartNotify\Support::setSetting('enabled', !empty($data['enabled']) ? '1' : '0');
        sn_response(200, ['ok' => true, 'message' => 'Settings saved.']);
    }
    if ($action === 'test') {
        $row = \WHMCS\Database\Capsule::table('mod_sn_subscriptions')->where('admin_id', $adminId)->where('is_active', 1)->first();
        if (!$row) sn_response(422, ['ok' => false, 'message' => 'Connect this browser before sending a test notification.']);
        \SmartNotify\Support::queueEvent(['event_type' => 'test', 'ticket_id' => 0, 'customer_id' => 0, 'subject' => 'SmartNotify test', 'message_excerpt' => 'Your browser push connection is working.', 'customer_name' => 'SmartNotify', 'message_id' => 'test-' . bin2hex(random_bytes(8))]);
        sn_response(200, ['ok' => true, 'message' => 'Test notification queued. Run cron to deliver it.']);
    }
    sn_response(400, ['ok' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[SmartNotify] API error: ' . get_class($e) . ' ' . $e->getMessage());
    sn_response(500, ['ok' => false, 'message' => 'SmartNotify could not complete the request. Check the server error log.']);
}
