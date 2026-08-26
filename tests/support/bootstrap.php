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
        last_login_at    TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE plans (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        code          TEXT NOT NULL UNIQUE,
        name          TEXT NOT NULL,
        description   TEXT NULL,
        price_monthly NUMERIC NULL,
        price_yearly  NUMERIC NULL,
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
        code       TEXT NOT NULL UNIQUE,
        name       TEXT NULL,
        is_active  INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
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
