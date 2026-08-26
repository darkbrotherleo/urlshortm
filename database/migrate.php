<?php
declare(strict_types=1);

/**
 * Migration idempotent (v1 + v2). Chạy bằng CLI:
 *   php database/migrate.php
 * Yêu cầu cấu hình trong config.local.php (hoặc env URLSHORTM_DB_*).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('URLSHORTM_APP', true);

require __DIR__ . '/../app/config.php';

$config = require __DIR__ . '/../app/config.php';
$db = $config['db'];

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s', $db['host'], $db['port']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . $db['name'] . '`');

// ---------- v1 ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS short_links (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(16)     NOT NULL,
    target_url    VARCHAR(2048)   NOT NULL,
    click_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    user_id       BIGINT UNSIGNED NULL,
    folder_id     BIGINT UNSIGNED NULL,
    link_type     VARCHAR(20)     NOT NULL DEFAULT \'link\',
    title         VARCHAR(255)    NULL,
    description   VARCHAR(500)    NULL,
    thumbnail     VARCHAR(2048)   NULL,
    pixels        TEXT            NULL,
    utm_campaign  VARCHAR(190)    NULL,
    utm_medium    VARCHAR(190)    NULL,
    utm_source    VARCHAR(190)    NULL,
    utm_term      VARCHAR(190)    NULL,
    utm_content   VARCHAR(190)    NULL,
    domain        VARCHAR(190)    NULL,
    password_hash VARCHAR(255)    NULL,
    starts_at     DATETIME        NULL,
    ends_at       DATETIME        NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_slug (slug),
    KEY idx_user (user_id),
    KEY idx_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash      VARCHAR(64)     NOT NULL,
    bucket_key   VARCHAR(32)     NOT NULL,
    window_start INT UNSIGNED    NOT NULL,
    count        INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bucket (ip_hash, bucket_key, window_start),
    KEY idx_window (window_start)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v2: users / plans / subscriptions ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email            VARCHAR(191)    NOT NULL,
    password_hash    VARCHAR(255)    NOT NULL,
    display_name     VARCHAR(100)    NULL,
    status           ENUM(\'active\',\'disabled\') NOT NULL DEFAULT \'active\',
    email_verified_at DATETIME       NULL,
    last_login_at    DATETIME        NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS plans (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(32)     NOT NULL,
    name          VARCHAR(100)    NOT NULL,
    description   VARCHAR(255)    NULL,
    price_monthly DECIMAL(10,2)   NULL,
    price_yearly  DECIMAL(10,2)   NULL,
    features      JSON            NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order    INT             NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS user_subscriptions (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    plan_id    BIGINT UNSIGNED NOT NULL,
    status     ENUM(\'trial\',\'active\',\'past_due\',\'canceled\',\'expired\') NOT NULL DEFAULT \'trial\',
    starts_at  DATETIME NULL,
    ends_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_plan (plan_id),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// short_links.user_id (cho DB cũ)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = ? AND table_name = 'short_links' AND column_name = 'user_id'"
);
$stmt->execute([$db['name']]);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE short_links ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER click_count');
    $pdo->exec('ALTER TABLE short_links ADD KEY idx_user (user_id)');
}

// ---------- v3: folders + short_links.folder_id ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS folders (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    name       VARCHAR(100)    NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = ? AND table_name = 'short_links' AND column_name = 'folder_id'"
);
$stmt->execute([$db['name']]);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE short_links ADD COLUMN folder_id BIGINT UNSIGNED NULL AFTER user_id');
    $pdo->exec('ALTER TABLE short_links ADD KEY idx_folder (folder_id)');
}

// ---------- v4: mở rộng short_links (metadata link, password, thời gian) ----------

$hasColumn = function (string $table, string $column) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$db['name'], $table, $column]);

    return (int) $stmt->fetchColumn() > 0;
};

$v4Columns = [
    'link_type'     => "VARCHAR(20) NOT NULL DEFAULT 'link'",
    'title'         => 'VARCHAR(255) NULL',
    'description'   => 'VARCHAR(500) NULL',
    'thumbnail'     => 'VARCHAR(2048) NULL',
    'pixels'        => 'TEXT NULL',
    'utm_campaign'  => 'VARCHAR(190) NULL',
    'utm_medium'    => 'VARCHAR(190) NULL',
    'utm_source'    => 'VARCHAR(190) NULL',
    'utm_term'      => 'VARCHAR(190) NULL',
    'utm_content'   => 'VARCHAR(190) NULL',
    'domain'        => 'VARCHAR(190) NULL',
    'password_hash' => 'VARCHAR(255) NULL',
    'starts_at'     => 'DATETIME NULL',
    'ends_at'       => 'DATETIME NULL',
    'updated_at'    => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];
foreach ($v4Columns as $column => $definition) {
    if (!$hasColumn('short_links', $column)) {
        $pdo->exec('ALTER TABLE short_links ADD COLUMN `' . $column . '` ' . $definition);
    }
}

// ---------- v5: bảng pixels (droplist chọn Pixel ID) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS pixels (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(64)     NOT NULL,
    name       VARCHAR(100)    NULL,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$seedPixels = [
    ['fb-pixel', 'Facebook Pixel', 10],
    ['ga4', 'Google Analytics 4', 20],
    ['gtm', 'Google Tag Manager', 30],
    ['tiktok', 'TikTok Pixel', 40],
    ['zalo-pixel', 'Zalo Pixel', 50],
    ['ads', 'Google Ads', 60],
];
$countPixels = (int) $pdo->query('SELECT COUNT(*) FROM pixels')->fetchColumn();
if ($countPixels === 0) {
    $insertPixel = $pdo->prepare('INSERT INTO pixels (code, name, is_active, sort_order) VALUES (?, ?, 1, ?)');
    foreach ($seedPixels as $row) {
        $insertPixel->execute($row);
    }
}

// Seed gói mặc định (free / starter / pro)
$countPlans = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
if ($countPlans === 0) {
    $seed = [
        ['free', 'Miễn phí', 'Bắt đầu dùng thử, không ràng buộc.', 0.00, 0.00,
         json_encode(['max_links' => 20, 'custom_slug' => false, 'stats' => true], JSON_UNESCAPED_UNICODE), 1, 10],
        ['starter', 'Starter', 'Cho người cần theo dõi nhiều link hơn.', 5.00, 50.00,
         json_encode(['max_links' => 200, 'custom_slug' => true, 'stats' => true], JSON_UNESCAPED_UNICODE), 1, 20],
        ['pro', 'Pro', 'Gói đầy đủ cho người dùng chuyên nghiệp.', 12.00, 120.00,
         json_encode(['max_links' => 2000, 'custom_slug' => true, 'stats_retention_days' => 365], JSON_UNESCAPED_UNICODE), 1, 30],
    ];
    $insert = $pdo->prepare(
        'INSERT INTO plans (code, name, description, price_monthly, price_yearly, features, is_active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($seed as $row) {
        $insert->execute($row);
    }
}

echo "PASS: migration ok (db={$db['name']})\n";
