<?php
/** @var string $siteName */
/** @var string $siteIntro */
/** @var string $logo */
/** @var string $favicon */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã lưu cấu hình.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Thông tin website</h2>
            <p class="a-card-sub">Tên, giới thiệu, logo và favicon hiển thị trên toàn bộ website.</p>
        </div>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/website/save') ?>" enctype="multipart/form-data">
        <div class="a-settings-body">
            <div class="a-field">
                <label for="site-name">Tên website</label>
                <input id="site-name" name="site_name" type="text" maxlength="100" value="<?= \App\escape($siteName) ?>">
            </div>
            <div class="a-field">
                <label for="site-intro">Giới thiệu website</label>
                <textarea id="site-intro" name="site_intro" rows="3"><?= \App\escape($siteIntro) ?></textarea>
                <span class="a-hint">Đoạn mô tả ngắn, thường dùng cho meta description mặc định và footer.</span>
            </div>
            <div class="a-grid-2">
                <div class="a-field">
                    <label>Logo</label>
                    <?php if ($logo !== ''): ?><img src="<?= \App\url_for('/' . $logo) ?>" alt="Logo" style="width:64px;height:64px;object-fit:contain;border:1px solid var(--aline);border-radius:10px;background:#F1F5F9;padding:4px;"><?php endif; ?>
                    <input name="logo" type="file" accept="image/*">
                </div>
                <div class="a-field">
                    <label>Favicon</label>
                    <?php if ($favicon !== ''): ?><img src="<?= \App\url_for('/' . $favicon) ?>" alt="Favicon" style="width:28px;height:28px;object-fit:contain;border:1px solid var(--aline);border-radius:6px;background:#F1F5F9;padding:3px;"><?php endif; ?>
                    <input name="favicon" type="file" accept="image/*">
                </div>
            </div>
        </div>
        <div class="a-settings-actions" style="padding:0 1.4rem 1.2rem;">
            <?= $csrf->field() ?>
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>
