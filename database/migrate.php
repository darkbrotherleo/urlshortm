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

// Xoá Pixel mặc định (user_id NULL) — chỉ giữ pixel do user tạo.
$pdo->exec('DELETE FROM pixels WHERE user_id IS NULL');

// ---------- v6: pixels theo user + domains + utm_profiles ----------

if (!$hasColumn('pixels', 'user_id')) {
    $pdo->exec('ALTER TABLE pixels ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id');
    $pdo->exec('ALTER TABLE pixels ADD KEY idx_user (user_id)');
}

// Bỏ unique theo code toàn cục, thay bằng unique (user_id, code)
$idx = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = '{$db['name']}' AND table_name = 'pixels' AND index_name = 'uq_code'"
);
if ((int) $idx->fetchColumn() > 0) {
    $pdo->exec('ALTER TABLE pixels DROP INDEX uq_code');
}
$idx2 = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = '{$db['name']}' AND table_name = 'pixels' AND index_name = 'uq_user_code'"
);
if ((int) $idx2->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE pixels ADD UNIQUE KEY uq_user_code (user_id, code)');
}

// ---------- v7: cột platform cho pixels ----------

if (!$hasColumn('pixels', 'platform')) {
    $pdo->exec("ALTER TABLE pixels ADD COLUMN platform VARCHAR(64) NULL AFTER name");
}

$pdo->exec('CREATE TABLE IF NOT EXISTS domains (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    domain      VARCHAR(190)    NOT NULL,
    is_verified TINYINT(1)      NOT NULL DEFAULT 0,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_domain_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v8: xác minh domain (verification token + trạng thái) ----------

foreach ([
    'verification_token' => 'VARCHAR(64) NULL',
    'verified_at'        => 'DATETIME NULL',
    'dns_checked_at'     => 'DATETIME NULL',
    'last_error'         => 'VARCHAR(255) NULL',
] as $column => $definition) {
    if (!$hasColumn('domains', $column)) {
        $pdo->exec('ALTER TABLE domains ADD COLUMN `' . $column . '` ' . $definition);
    }
}

// ---------- v9: click_events (tracking chi tiết mỗi lượt mở) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS click_events (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    link_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NULL,
    opened_at  DATETIME        NOT NULL,
    ip_hash    VARCHAR(64)     NOT NULL,
    ip_address VARCHAR(45)     NULL,
    user_agent VARCHAR(512)    NULL,
    referrer   VARCHAR(512)    NULL,
    country    VARCHAR(2)      NULL,
    device     VARCHAR(32)     NULL,
    browser    VARCHAR(64)     NULL,
    os         VARCHAR(64)     NULL,
    PRIMARY KEY (id),
    KEY idx_user_time (user_id, opened_at),
    KEY idx_link (link_id),
    CONSTRAINT fk_click_link FOREIGN KEY (link_id) REFERENCES short_links(id) ON DELETE CASCADE,
    CONSTRAINT fk_click_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v11: click_events lưu IP thật (có quyền thu thập) ----------

if (!$hasColumn('click_events', 'ip_address')) {
    $pdo->exec("ALTER TABLE click_events ADD COLUMN ip_address VARCHAR(45) NULL AFTER ip_hash");
}

// ---------- v12: hồ sơ user chi tiết + hoá đơn (mã số thuế) ----------

$v12Columns = [
    'phone'        => 'VARCHAR(20) NULL',
    'address'      => 'VARCHAR(255) NULL',
    'city'         => 'VARCHAR(100) NULL',
    'tax_type'     => 'VARCHAR(20) NULL',
    'company_name' => 'VARCHAR(190) NULL',
    'tax_id'       => 'VARCHAR(30) NULL',
    'invoice_name' => 'VARCHAR(190) NULL',
];
foreach ($v12Columns as $column => $definition) {
    if (!$hasColumn('users', $column)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN `' . $column . '` ' . $definition);
    }
}

// ---------- v13: bảng quản trị viên (Admin) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS admins (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(191)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    display_name  VARCHAR(100)    NULL,
    role          VARCHAR(32)     NOT NULL DEFAULT \'admin\',
    permissions   TEXT            NULL,
    status        ENUM(\'active\',\'disabled\') NOT NULL DEFAULT \'active\',
    last_login_at DATETIME        NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// Seed admin mặc định (chỉ chạy khi chưa tồn tại — idempotent).
$seedAdmin = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
$seedAdmin->execute(['darkbrotherleo@gmail.com']);
if ((int) $seedAdmin->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
        ->execute([
            'darkbrotherleo@gmail.com',
            password_hash('Mylinhtran12!', PASSWORD_DEFAULT),
            'Quản trị hệ thống',
            'super_admin',
        ]);
}

// ---------- v10: user_settings + demographic_snapshots (GĐ4 nhân khẩu học) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS user_settings (
    user_id    BIGINT UNSIGNED NOT NULL,
    skey       VARCHAR(64)     NOT NULL,
    svalue     TEXT            NULL,
    updated_at TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, skey),
    CONSTRAINT fk_setting_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS demographic_snapshots (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    payload    JSON            NULL,
    fetched_at DATETIME        NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_demo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS utm_profiles (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(100)    NOT NULL,
    utm_campaign VARCHAR(190)    NULL,
    utm_medium   VARCHAR(190)    NULL,
    utm_source   VARCHAR(190)    NULL,
    utm_term     VARCHAR(190)    NULL,
    utm_content  VARCHAR(190)    NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_utm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

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

// ---------- v14: mở rộng plans -> gói dịch vụ đầy đủ (Packages) ----------

$v14Columns = [
    'currency'                => "VARCHAR(10) NOT NULL DEFAULT 'VND'",
    'billing_period'          => "ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly'",
    'price'                   => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
    'max_links'               => 'INT NOT NULL DEFAULT 0',
    'max_clicks'              => 'INT NOT NULL DEFAULT 0',
    'max_custom_domains'      => 'INT NOT NULL DEFAULT 0',
    'max_pixels'              => 'INT NOT NULL DEFAULT 0',
    'max_users'               => 'INT NOT NULL DEFAULT 1',
    'has_analytics'           => 'TINYINT(1) NOT NULL DEFAULT 0',
    'has_qr_code'             => 'TINYINT(1) NOT NULL DEFAULT 0',
    'has_password_protection' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'has_link_expiration'     => 'TINYINT(1) NOT NULL DEFAULT 0',
    'has_utm_builder'         => 'TINYINT(1) NOT NULL DEFAULT 0',
    'has_api_access'          => 'TINYINT(1) NOT NULL DEFAULT 0',
    'is_popular'              => 'TINYINT(1) NOT NULL DEFAULT 0',
];
foreach ($v14Columns as $column => $definition) {
    if (!$hasColumn('plans', $column)) {
        $pdo->exec('ALTER TABLE plans ADD COLUMN `' . $column . '` ' . $definition);
    }
}

// Upsert 4 gói mặc định theo đặc tả (free / starter / pro / team).
$defaultPlans = [
    ['free',   'Miễn phí', 'Bắt đầu dùng thử miễn phí, không ràng buộc.', 0,      'monthly', 20, 10000, 5, 5,  1, 1,1,1,1,1,0, 0, 1, 10],
    ['starter','Starter', 'Cho người cần theo dõi nhiều link hơn.',        149000, 'monthly', 500, 50000, 3, 5,  1, 1,1,1,1,1,0, 1, 1, 20],
    ['pro',    'Pro',     'Gói đầy đủ cho người dùng chuyên nghiệp.',      399000, 'monthly', -1, -1,    10, -1, 3, 1,1,1,1,1,1, 0, 1, 30],
    ['team',   'Team',    'Cho đội nhóm nhiều thành viên.',                899000, 'monthly', -1, -1,    20, -1, 10,1,1,1,1,1,1, 0, 1, 40],
];
$upsertPlan = $pdo->prepare('SELECT COUNT(*) FROM plans WHERE code = ?');
$insPlan = $pdo->prepare(
    'INSERT INTO plans (code, name, description, price, price_monthly, billing_period, currency,
        max_links, max_clicks, max_custom_domains, max_pixels, max_users,
        has_analytics, has_qr_code, has_password_protection, has_link_expiration, has_utm_builder, has_api_access,
        is_popular, is_active, sort_order)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$updPlan = $pdo->prepare(
    'UPDATE plans SET name=?, description=?, price=?, price_monthly=?, billing_period=?, currency=?,
        max_links=?, max_clicks=?, max_custom_domains=?, max_pixels=?, max_users=?,
        has_analytics=?, has_qr_code=?, has_password_protection=?, has_link_expiration=?, has_utm_builder=?, has_api_access=?,
        is_popular=?, is_active=?, sort_order=?
     WHERE code=?'
);
foreach ($defaultPlans as $p) {
    [$code, $name, $desc, $price, $period, $links, $clicks, $domains, $pixels, $users,
     $an, $qr, $pass, $exp, $utm, $api, $popular, $active, $sort] = $p;
    $values = [$name, $desc, $price, $price, $period, 'VND', $links, $clicks, $domains, $pixels, $users,
               $an, $qr, $pass, $exp, $utm, $api, $popular, $active, $sort];
    $upsertPlan->execute([$code]);
    if ((int) $upsertPlan->fetchColumn() > 0) {
        $updPlan->execute([...$values, $code]);
    } else {
        $insPlan->execute([$code, ...$values]);
    }
}

// ---------- v15: đơn hàng + cấu hình cổng thanh toán ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS orders (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code       VARCHAR(32)     NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    plan_id          BIGINT UNSIGNED NOT NULL,
    plan_name        VARCHAR(100)    NOT NULL,
    billing_period   VARCHAR(20)     NOT NULL DEFAULT \'monthly\',
    amount           DECIMAL(12,2)   NOT NULL,
    currency         VARCHAR(10)     NOT NULL DEFAULT \'VND\',
    status           ENUM(\'pending\',\'paid\',\'canceled\',\'failed\') NOT NULL DEFAULT \'pending\',
    payment_method   VARCHAR(32)     NOT NULL DEFAULT \'paypal\',
    gateway_order_id VARCHAR(64)     NULL,
    payer            VARCHAR(191)    NULL,
    paid_at          DATETIME        NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_code (order_code),
    KEY idx_user (user_id),
    KEY idx_plan (plan_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS settings (
    skey       VARCHAR(64)  NOT NULL,
    svalue     TEXT         NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (skey)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v16: link khả dụng / vô hiệu (admin) ----------

if (!$hasColumn('short_links', 'is_active')) {
    $pdo->exec("ALTER TABLE short_links ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER ends_at");
}

// ---------- v17: voucher giảm giá ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS vouchers (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code           VARCHAR(50)     NOT NULL,
    campaign_name  VARCHAR(190)    NULL,
    discount_type  ENUM(\'percent\',\'fixed\') NOT NULL DEFAULT \'percent\',
    discount_value DECIMAL(12,2)   NOT NULL DEFAULT 0,
    usage_limit    INT             NOT NULL DEFAULT 1,
    used_count     INT             NOT NULL DEFAULT 0,
    per_user       ENUM(\'once\',\'multiple\') NOT NULL DEFAULT \'once\',
    starts_at      DATETIME        NULL,
    ends_at        DATETIME        NULL,
    note           TEXT            NULL,
    is_active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_voucher_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$pdo->exec('CREATE TABLE IF NOT EXISTS voucher_usages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id    BIGINT UNSIGNED NOT NULL,
    order_id      BIGINT UNSIGNED NULL,
    user_id       BIGINT UNSIGNED NULL,
    status        ENUM(\'success\',\'failed\') NOT NULL DEFAULT \'success\',
    amount_before DECIMAL(12,2)   NOT NULL DEFAULT 0,
    amount_after  DECIMAL(12,2)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_voucher (voucher_id),
    KEY idx_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v18: domain hệ thống (rút gọn link) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS system_domains (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain     VARCHAR(190)    NOT NULL,
    is_default TINYINT(1)      NOT NULL DEFAULT 0,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sys_domain (domain)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v19: media (quản lý ảnh upload) ----------

$pdo->exec('CREATE TABLE IF NOT EXISTS media (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename      VARCHAR(190)    NOT NULL,
    original_name VARCHAR(255)    NOT NULL,
    path          VARCHAR(255)    NOT NULL,
    mime          VARCHAR(100)    NULL,
    size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_filename (filename)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

// ---------- v20: kích hoạt tài khoản + lấy lại mật khẩu ----------

$pdo->exec("ALTER TABLE users MODIFY status ENUM('active','pending','disabled') NOT NULL DEFAULT 'active'");

foreach ([
    'activation_token'   => 'VARCHAR(64) NULL',
    'activation_expires_at' => 'DATETIME NULL',
    'reset_token'        => 'VARCHAR(64) NULL',
    'reset_expires_at'   => 'DATETIME NULL',
] as $column => $definition) {
    if (!$hasColumn('users', $column)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN `' . $column . '` ' . $definition);
    }
}

echo "PASS: migration ok (db={$db['name']})\n";
