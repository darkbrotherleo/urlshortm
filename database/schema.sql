-- Schema v2 - URL Shortener Micro
-- MySQL 8.4, utf8mb4

CREATE DATABASE IF NOT EXISTS urlshortm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE urlshortm;

CREATE TABLE IF NOT EXISTS short_links (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(16)     NOT NULL,
    target_url    VARCHAR(2048)   NOT NULL,
    click_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    user_id       BIGINT UNSIGNED NULL,
    folder_id     BIGINT UNSIGNED NULL,
    link_type     VARCHAR(20)     NOT NULL DEFAULT 'link',
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
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_slug (slug),
    KEY idx_user (user_id),
    KEY idx_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS folders (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    name       VARCHAR(100)    NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash      VARCHAR(64)     NOT NULL,
    bucket_key   VARCHAR(32)     NOT NULL,
    window_start INT UNSIGNED    NOT NULL,
    count        INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bucket (ip_hash, bucket_key, window_start),
    KEY idx_window (window_start)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email            VARCHAR(191)    NOT NULL,
    password_hash    VARCHAR(255)    NOT NULL,
    display_name     VARCHAR(100)    NULL,
    status           ENUM('active','pending','disabled') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME       NULL,
    activation_token VARCHAR(64)     NULL,
    activation_expires_at DATETIME   NULL,
    reset_token      VARCHAR(64)     NULL,
    reset_expires_at DATETIME        NULL,
    last_login_at    DATETIME        NULL,
    phone            VARCHAR(20)     NULL,
    address          VARCHAR(255)    NULL,
    city             VARCHAR(100)    NULL,
    tax_type         VARCHAR(20)     NULL,
    company_name     VARCHAR(190)    NULL,
    tax_id           VARCHAR(30)     NULL,
    invoice_name     VARCHAR(190)    NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(100)    NOT NULL,
    name          VARCHAR(100)    NOT NULL,
    description   TEXT            NULL,
    price         DECIMAL(12,2)   NOT NULL DEFAULT 0,
    price_monthly DECIMAL(10,2)   NULL,
    price_yearly  DECIMAL(10,2)   NULL,
    currency      VARCHAR(10)     NOT NULL DEFAULT 'VND',
    billing_period ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
    max_links     INT             NOT NULL DEFAULT 0,
    max_clicks    INT             NOT NULL DEFAULT 0,
    max_custom_domains INT        NOT NULL DEFAULT 0,
    max_pixels    INT             NOT NULL DEFAULT 0,
    max_users     INT             NOT NULL DEFAULT 1,
    has_analytics TINYINT(1)      NOT NULL DEFAULT 0,
    has_qr_code   TINYINT(1)      NOT NULL DEFAULT 0,
    has_password_protection TINYINT(1) NOT NULL DEFAULT 0,
    has_link_expiration TINYINT(1) NOT NULL DEFAULT 0,
    has_utm_builder TINYINT(1)    NOT NULL DEFAULT 0,
    has_api_access TINYINT(1)     NOT NULL DEFAULT 0,
    is_popular    TINYINT(1)      NOT NULL DEFAULT 0,
    features      JSON            NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order    INT             NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    plan_id    BIGINT UNSIGNED NOT NULL,
    status     ENUM('trial','active','past_due','canceled','expired') NOT NULL DEFAULT 'trial',
    starts_at  DATETIME NULL,
    ends_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_plan (plan_id),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pixels (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    code       VARCHAR(64)     NOT NULL,
    name       VARCHAR(100)    NULL,
    platform   VARCHAR(64)     NULL,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_code (user_id, code),
    KEY idx_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            BIGINT UNSIGNED NOT NULL,
    domain             VARCHAR(190)    NOT NULL,
    is_verified        TINYINT(1)      NOT NULL DEFAULT 0,
    is_active          TINYINT(1)      NOT NULL DEFAULT 1,
    verification_token VARCHAR(64)     NULL,
    verified_at        DATETIME        NULL,
    dns_checked_at     DATETIME        NULL,
    last_error         VARCHAR(255)    NULL,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_domain_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utm_profiles (
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Pixel là của user tự tạo; không còn pixel mặc định (seed).

CREATE TABLE IF NOT EXISTS click_events (
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(191)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    display_name  VARCHAR(100)    NULL,
    role          VARCHAR(32)     NOT NULL DEFAULT 'admin',
    permissions   TEXT            NULL,
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME        NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code       VARCHAR(32)     NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    plan_id          BIGINT UNSIGNED NOT NULL,
    plan_name        VARCHAR(100)    NOT NULL,
    billing_period   VARCHAR(20)     NOT NULL DEFAULT 'monthly',
    amount           DECIMAL(12,2)   NOT NULL,
    currency         VARCHAR(10)     NOT NULL DEFAULT 'VND',
    status           ENUM('pending','paid','canceled','failed') NOT NULL DEFAULT 'pending',
    payment_method   VARCHAR(32)     NOT NULL DEFAULT 'paypal',
    gateway_order_id VARCHAR(64)     NULL,
    payer            VARCHAR(191)    NULL,
    paid_at          DATETIME        NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_code (order_code),
    KEY idx_user (user_id),
    KEY idx_plan (plan_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    skey       VARCHAR(64)  NOT NULL,
    svalue     TEXT         NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (skey)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code           VARCHAR(50)     NOT NULL,
    campaign_name  VARCHAR(190)    NULL,
    discount_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(12,2)   NOT NULL DEFAULT 0,
    usage_limit    INT             NOT NULL DEFAULT 1,
    used_count     INT             NOT NULL DEFAULT 0,
    per_user       ENUM('once','multiple') NOT NULL DEFAULT 'once',
    starts_at      DATETIME        NULL,
    ends_at        DATETIME        NULL,
    note           TEXT            NULL,
    is_active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_voucher_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voucher_usages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id    BIGINT UNSIGNED NOT NULL,
    order_id      BIGINT UNSIGNED NULL,
    user_id       BIGINT UNSIGNED NULL,
    status        ENUM('success','failed') NOT NULL DEFAULT 'success',
    amount_before DECIMAL(12,2)   NOT NULL DEFAULT 0,
    amount_after  DECIMAL(12,2)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_voucher (voucher_id),
    KEY idx_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_domains (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain     VARCHAR(190)    NOT NULL,
    is_default TINYINT(1)      NOT NULL DEFAULT 0,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sys_domain (domain)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename      VARCHAR(190)    NOT NULL,
    original_name VARCHAR(255)    NOT NULL,
    path          VARCHAR(255)    NOT NULL,
    mime          VARCHAR(100)    NULL,
    size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_filename (filename)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_settings (
    user_id    BIGINT UNSIGNED NOT NULL,
    skey       VARCHAR(64)     NOT NULL,
    svalue     TEXT            NULL,
    updated_at TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, skey),
    CONSTRAINT fk_setting_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demographic_snapshots (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    payload    JSON            NULL,
    fetched_at DATETIME        NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_demo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Seed gói mặc định (chạy một lần; migrate.php lo việc này nếu bảng rỗng)
INSERT INTO plans (code, name, description, price_monthly, price_yearly, features, is_active, sort_order)
VALUES
    ('free', 'Miễn phí', 'Bắt đầu dùng thử, không ràng buộc.', 0.00, 0.00, '{"max_links":20,"custom_slug":false,"stats":true}', 1, 10),
    ('starter', 'Starter', 'Cho người cần theo dõi nhiều link hơn.', 5.00, 50.00, '{"max_links":200,"custom_slug":true,"stats":true}', 1, 20),
    ('pro', 'Pro', 'Gói đầy đủ cho người dùng chuyên nghiệp.', 12.00, 120.00, '{"max_links":2000,"custom_slug":true,"stats_retention_days":365}', 1, 30)
ON DUPLICATE KEY UPDATE name = VALUES(name);
