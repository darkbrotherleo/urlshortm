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
    status           ENUM('active','disabled') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME       NULL,
    last_login_at    DATETIME        NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
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
    code       VARCHAR(64)     NOT NULL,
    name       VARCHAR(100)    NULL,
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Seed pixels mặc định (migrate.php lo nếu bảng rỗng)
INSERT INTO pixels (code, name, is_active, sort_order)
VALUES
    ('fb-pixel', 'Facebook Pixel', 1, 10),
    ('ga4', 'Google Analytics 4', 1, 20),
    ('gtm', 'Google Tag Manager', 1, 30),
    ('tiktok', 'TikTok Pixel', 1, 40),
    ('zalo-pixel', 'Zalo Pixel', 1, 50),
    ('ads', 'Google Ads', 1, 60)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Seed gói mặc định (chạy một lần; migrate.php lo việc này nếu bảng rỗng)
INSERT INTO plans (code, name, description, price_monthly, price_yearly, features, is_active, sort_order)
VALUES
    ('free', 'Miễn phí', 'Bắt đầu dùng thử, không ràng buộc.', 0.00, 0.00, '{"max_links":20,"custom_slug":false,"stats":true}', 1, 10),
    ('starter', 'Starter', 'Cho người cần theo dõi nhiều link hơn.', 5.00, 50.00, '{"max_links":200,"custom_slug":true,"stats":true}', 1, 20),
    ('pro', 'Pro', 'Gói đầy đủ cho người dùng chuyên nghiệp.', 12.00, 120.00, '{"max_links":2000,"custom_slug":true,"stats_retention_days":365}', 1, 30)
ON DUPLICATE KEY UPDATE name = VALUES(name);
