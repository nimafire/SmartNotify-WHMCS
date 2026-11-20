<?php
namespace SmartNotify;

use WHMCS\Database\Capsule;

class Support
{
    public static function config(): array
    {
        $file = dirname(__DIR__) . '/config.php';
        $values = is_file($file) ? require $file : [];
        if (!is_array($values)) $values = [];
        return array_merge(['vapid_subject' => 'mailto:admin@example.com', 'vapid_public_key' => '', 'vapid_private_key' => '', 'message_length' => 180, 'enabled' => true], $values);
    }

    public static function install(): void
    {
        $schema = Capsule::schema();
        if (!$schema->hasTable('mod_sn_subscriptions')) $schema->create('mod_sn_subscriptions', function ($t) { $t->increments('id'); $t->unsignedInteger('admin_id'); $t->string('endpoint_hash', 64)->unique(); $t->text('endpoint'); $t->text('public_key'); $t->text('auth_token'); $t->boolean('is_active')->default(true); $t->timestamp('last_seen_at')->nullable(); $t->timestamps(); $t->index(['admin_id', 'is_active']); });
        if (!$schema->hasTable('mod_sn_events')) $schema->create('mod_sn_events', function ($t) { $t->increments('id'); $t->string('event_key', 64)->unique(); $t->unsignedInteger('ticket_id'); $t->string('event_type', 40); $t->unsignedInteger('customer_id')->default(0); $t->string('subject', 255)->nullable(); $t->text('message_excerpt')->nullable(); $t->string('customer_name', 255)->nullable(); $t->string('status', 20)->default('pending'); $t->unsignedTinyInteger('attempts')->default(0); $t->timestamp('created_at')->useCurrent(); $t->timestamp('processed_at')->nullable(); $t->index(['status', 'created_at']); });
        if ($schema->hasTable('mod_sn_events') && !$schema->hasColumn('mod_sn_events', 'attempts')) $schema->table('mod_sn_events', function ($t) { $t->unsignedTinyInteger('attempts')->default(0); });
        if (!$schema->hasTable('mod_sn_delivery_logs')) $schema->create('mod_sn_delivery_logs', function ($t) { $t->increments('id'); $t->unsignedInteger('event_id'); $t->unsignedInteger('subscription_id'); $t->unsignedInteger('admin_id'); $t->string('status', 20); $t->unsignedSmallInteger('response_code')->nullable(); $t->string('error_message', 500)->nullable(); $t->timestamp('attempted_at')->useCurrent(); $t->index(['event_id', 'status']); });
        if (!$schema->hasTable('mod_sn_settings')) $schema->create('mod_sn_settings', function ($t) { $t->increments('id'); $t->string('setting_key', 100)->unique(); $t->text('setting_value')->nullable(); });
    }

    public static function moduleUrl(): string
    {
        $system = rtrim((string) (\WHMCS\Config\Setting::getValue('SystemURL') ?: ''), '/') . '/';
        return $system . 'modules/addons/smartnotify/';
    }

    public static function whmcsRootUrl(): string
    {
        return rtrim((string) (\WHMCS\Config\Setting::getValue('SystemURL') ?: ''), '/') . '/';
    }

    public static function detectAdminPath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = basename(dirname($script));
        return $dir && $dir !== '.' ? $dir : 'admin';
    }

    public static function setSetting(string $key, string $value): void
    {
        Capsule::table('mod_sn_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
    }

    public static function setting(string $key, string $default = ''): string
    {
        return (string) (Capsule::table('mod_sn_settings')->where('setting_key', $key)->value('setting_value') ?? $default);
    }

    public static function stats(): array
    {
        $active = Capsule::table('mod_sn_subscriptions')->where('is_active', 1)->count();
        $browsers = Capsule::table('mod_sn_subscriptions')->distinct()->count('endpoint_hash');
        $last = Capsule::table('mod_sn_events')->orderByDesc('id')->value('created_at');
        $success = Capsule::table('mod_sn_delivery_logs')->where('status', 'sent')->orderByDesc('id')->value('attempted_at');
        return ['active' => $active, 'browsers' => $browsers, 'last_event' => $last, 'last_success' => $success];
    }

    public static function subscriptions()
    {
        return Capsule::table('mod_sn_subscriptions as s')->leftJoin('tbladmins as a', 'a.id', '=', 's.admin_id')->select('s.*', Capsule::raw("COALESCE(a.username, CONCAT('Admin #', s.admin_id)) as admin_username"))->orderByDesc('s.id')->limit(500)->get();
    }

    public static function eventLogs()
    {
        return Capsule::table('mod_sn_events as e')->leftJoin('mod_sn_delivery_logs as d', 'd.event_id', '=', 'e.id')->select('e.*', Capsule::raw("SUM(CASE WHEN d.status='sent' THEN 1 ELSE 0 END) as sent_count"), Capsule::raw("SUM(CASE WHEN d.status='failed' THEN 1 ELSE 0 END) as failed_count"))->groupBy('e.id')->orderByDesc('e.id')->limit(200)->get();
    }

    public static function queueEvent(array $data): void
    {
        $key = hash('sha256', implode('|', [$data['event_type'], $data['ticket_id'], $data['message_id']]));
        Capsule::table('mod_sn_events')->insertOrIgnore(['event_key' => $key, 'ticket_id' => $data['ticket_id'], 'event_type' => $data['event_type'], 'customer_id' => $data['customer_id'], 'subject' => $data['subject'], 'message_excerpt' => $data['message_excerpt'], 'customer_name' => $data['customer_name'], 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);
    }
}
