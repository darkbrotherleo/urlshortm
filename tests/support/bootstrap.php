<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('URLSHORTM_APP', true);

// Khởi tạo session ngay (trước mọi output) để AuthService test được sạch.
session_start();

$root = dirname(__DIR__, 2);

spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = $root . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

require_once $root . '/app/config.php';
require_once $root . '/app/helpers.php';
require_once $root . '/app/db.php';

\App\Config::load(require $root . '/app/config.php');

/**
 * Tạo PDO SQLite in-memory với schema khớp v1 (portable).
 */
function make_sqlite(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec('CREATE TABLE short_links (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        slug          TEXT NOT NULL UNIQUE,
        target_url    TEXT NOT NULL,
        click_count   INTEGER NOT NULL DEFAULT 0,
        user_id       INTEGER NULL,
        folder_id     INTEGER NULL,
        link_type     TEXT NOT NULL DEFAULT \'link\',
        title         TEXT NULL,
        description   TEXT NULL,
        thumbnail     TEXT NULL,
        pixels        TEXT NULL,
        utm_campaign  TEXT NULL,
        utm_medium    TEXT NULL,
        utm_source    TEXT NULL,
        utm_term      TEXT NULL,
        utm_content   TEXT NULL,
        domain        TEXT NULL,
        password_hash TEXT NULL,
        starts_at     TEXT NULL,
        ends_at       TEXT NULL,
        is_active     INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at    TEXT NULL
    )');

    $pdo->exec('CREATE TABLE rate_limits (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash      TEXT NOT NULL,
        bucket_key   TEXT NOT NULL,
        window_start INTEGER NOT NULL,
        count        INTEGER NOT NULL DEFAULT 0,
        UNIQUE (ip_hash, bucket_key, window_start)
    )');

    $pdo->exec('CREATE TABLE users (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        email            TEXT NOT NULL UNIQUE,
        password_hash    TEXT NOT NULL,
        display_name     TEXT NULL,
        status           TEXT NOT NULL DEFAULT \'active\',
        email_verified_at TEXT NULL,
        activation_token TEXT NULL,
        activation_expires_at TEXT NULL,
        reset_token      TEXT NULL,
        reset_expires_at TEXT NULL,
        last_login_at    TEXT NULL,
        phone            TEXT NULL,
        address          TEXT NULL,
        city             TEXT NULL,
        tax_type         TEXT NULL,
        company_name     TEXT NULL,
        tax_id           TEXT NULL,
        invoice_name     TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE admins (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        email         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name  TEXT NULL,
        role          TEXT NOT NULL DEFAULT \'admin\',
        permissions   TEXT NULL,
        status        TEXT NOT NULL DEFAULT \'active\',
        last_login_at TEXT NULL,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE plans (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        code          TEXT NOT NULL UNIQUE,
        name          TEXT NOT NULL,
        description   TEXT NULL,
        price         NUMERIC NOT NULL DEFAULT 0,
        price_monthly NUMERIC NULL,
        price_yearly  NUMERIC NULL,
        currency      TEXT NOT NULL DEFAULT \'VND\',
        billing_period TEXT NOT NULL DEFAULT \'monthly\',
        max_links     INTEGER NOT NULL DEFAULT 0,
        max_clicks    INTEGER NOT NULL DEFAULT 0,
        max_custom_domains INTEGER NOT NULL DEFAULT 0,
        max_pixels    INTEGER NOT NULL DEFAULT 0,
        max_users     INTEGER NOT NULL DEFAULT 1,
        has_analytics INTEGER NOT NULL DEFAULT 0,
        has_qr_code   INTEGER NOT NULL DEFAULT 0,
        has_password_protection INTEGER NOT NULL DEFAULT 0,
        has_link_expiration INTEGER NOT NULL DEFAULT 0,
        has_utm_builder INTEGER NOT NULL DEFAULT 0,
        has_api_access INTEGER NOT NULL DEFAULT 0,
        is_popular    INTEGER NOT NULL DEFAULT 0,
        features      TEXT NULL,
        is_active     INTEGER NOT NULL DEFAULT 1,
        sort_order    INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE user_subscriptions (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        plan_id    INTEGER NOT NULL,
        status     TEXT NOT NULL DEFAULT \'trial\',
        starts_at  TEXT NULL,
        ends_at    TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE folders (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        name       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE pixels (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NULL,
        code       TEXT NOT NULL,
        name       TEXT NULL,
        platform   TEXT NULL,
        is_active  INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        UNIQUE (user_id, code)
    )');

    $pdo->exec('CREATE TABLE domains (
        id                 INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id            INTEGER NOT NULL,
        domain             TEXT NOT NULL,
        is_verified        INTEGER NOT NULL DEFAULT 0,
        is_active          INTEGER NOT NULL DEFAULT 1,
        verification_token TEXT NULL,
        verified_at        TEXT NULL,
        dns_checked_at     TEXT NULL,
        last_error         TEXT NULL,
        created_at         TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE utm_profiles (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL,
        name         TEXT NOT NULL,
        utm_campaign TEXT NULL,
        utm_medium   TEXT NULL,
        utm_source   TEXT NULL,
        utm_term     TEXT NULL,
        utm_content  TEXT NULL,
        created_at   TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE click_events (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        link_id    INTEGER NOT NULL,
        user_id    INTEGER NULL,
        opened_at  TEXT NOT NULL,
        ip_hash    TEXT NOT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        referrer   TEXT NULL,
        country    TEXT NULL,
        device     TEXT NULL,
        browser    TEXT NULL,
        os         TEXT NULL
    )');

    $pdo->exec('CREATE TABLE user_settings (
        user_id    INTEGER NOT NULL,
        skey       TEXT NOT NULL,
        svalue     TEXT NULL,
        updated_at TEXT NULL,
        PRIMARY KEY (user_id, skey)
    )');

    $pdo->exec('CREATE TABLE settings (
        skey       TEXT NOT NULL,
        svalue     TEXT NULL,
        updated_at TEXT NULL,
        PRIMARY KEY (skey)
    )');

    $pdo->exec('CREATE TABLE orders (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        order_code       TEXT NOT NULL UNIQUE,
        user_id          INTEGER NOT NULL,
        plan_id          INTEGER NOT NULL,
        plan_name        TEXT NOT NULL,
        billing_period   TEXT NOT NULL DEFAULT \'monthly\',
        amount           NUMERIC NOT NULL,
        currency         TEXT NOT NULL DEFAULT \'VND\',
        status           TEXT NOT NULL DEFAULT \'pending\',
        payment_method   TEXT NOT NULL DEFAULT \'paypal\',
        gateway_order_id TEXT NULL,
        payer            TEXT NULL,
        paid_at          TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE vouchers (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        code           TEXT NOT NULL UNIQUE,
        campaign_name  TEXT NULL,
        discount_type  TEXT NOT NULL DEFAULT \'percent\',
        discount_value NUMERIC NOT NULL DEFAULT 0,
        usage_limit    INTEGER NOT NULL DEFAULT 1,
        used_count     INTEGER NOT NULL DEFAULT 0,
        per_user       TEXT NOT NULL DEFAULT \'once\',
        starts_at      TEXT NULL,
        ends_at        TEXT NULL,
        note           TEXT NULL,
        is_active      INTEGER NOT NULL DEFAULT 1,
        created_at     TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at     TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE voucher_usages (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_id    INTEGER NOT NULL,
        order_id      INTEGER NULL,
        user_id       INTEGER NULL,
        status        TEXT NOT NULL DEFAULT \'success\',
        amount_before NUMERIC NOT NULL DEFAULT 0,
        amount_after  NUMERIC NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE media (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        filename      TEXT NOT NULL UNIQUE,
        original_name TEXT NOT NULL,
        path          TEXT NOT NULL,
        mime          TEXT NULL,
        size          INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE system_domains (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        domain     TEXT NOT NULL UNIQUE,
        is_default INTEGER NOT NULL DEFAULT 0,
        is_active  INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE demographic_snapshots (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        payload    TEXT NULL,
        fetched_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    return $pdo;
}

final class TestSuite
{
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function test(string $name, callable $fn): void
    {
        $this->tests[] = ['name' => $name, 'fn' => $fn];
    }

    public function run(): int
    {
        foreach ($this->tests as $t) {
            try {
                $t['fn']();
                $this->passed++;
                echo "  PASS  {$t['name']}\n";
            } catch (Throwable $e) {
                $this->failed++;
                $this->failures[] = "{$t['name']}: {$e->getMessage()}";
                echo "  FAIL  {$t['name']}: {$e->getMessage()}\n";
            }
        }

        echo "\n{$this->passed} passed, {$this->failed} failed\n";

        return $this->failed === 0 ? 0 : 1;
    }
}

function assert_true(bool $cond, string $msg = 'expected true'): void
{
    if ($cond !== true) {
        throw new RuntimeException($msg);
    }
}

function assert_false(bool $cond, string $msg = 'expected false'): void
{
    if ($cond !== false) {
        throw new RuntimeException($msg);
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($msg !== '' ? $msg . ': ' : '') . 'expected ' . var_export($expected, true)
            . ' got ' . var_export($actual, true)
        );
    }
}

function assert_null(mixed $value, string $msg = 'expected null'): void
{
    if ($value !== null) {
        throw new RuntimeException($msg . ', got ' . var_export($value, true));
    }
}

function assert_matches(string $pattern, string $value, string $msg = ''): void
{
    if (preg_match($pattern, $value) !== 1) {
        throw new RuntimeException(($msg !== '' ? $msg . ': ' : '') . 'value does not match ' . $pattern . ': ' . $value);
    }
}

function assert_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($msg !== '' ? $msg . ': ' : '') . 'missing "' . $needle . '" in: ' . $haystack);
    }
}

final class AlwaysAllowLimiter extends \App\Security\RateLimiter
{
    public function allow(string $key, string $ip, int $limit, int $windowSeconds): bool
    {
        return true;
    }
}

final class AlwaysDenyLimiter extends \App\Security\RateLimiter
{
    public function allow(string $key, string $ip, int $limit, int $windowSeconds): bool
    {
        return false;
    }
}
