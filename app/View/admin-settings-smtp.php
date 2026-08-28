<?php
/** @var string $smtp_host */
/** @var string $smtp_port */
/** @var string $smtp_username */
/** @var string $smtp_password */
/** @var string $smtp_from_email */
/** @var bool $smtp_configured */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã lưu cấu hình / gửi thử thành công.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Email (SMTP)</h2>
            <p class="a-card-sub">Cấu hình máy chủ gửi email — ví dụ Gmail: smtp.gmail.com : 587 (STARTTLS).</p>
        </div>
        <span class="a-pill <?= $smtp_configured ? 'ok' : 'warn' ?>"><?= $smtp_configured ? 'Đã cấu hình' : 'Chưa cấu hình' ?></span>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/smtp/save') ?>">
        <div class="a-settings-body">
            <div class="a-grid-2">
                <div class="a-field">
                    <label for="smtp-host">Máy chủ SMTP</label>
                    <input id="smtp-host" name="smtp_host" type="text" value="<?= \App\escape($smtp_host) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="a-field">
                    <label for="smtp-port">Cổng SMTP</label>
                    <input id="smtp-port" name="smtp_port" type="text" value="<?= \App\escape($smtp_port !== '' ? $smtp_port : '587') ?>">
                    <span class="a-hint">587 (STARTTLS) hoặc 465 (SSL).</span>
                </div>
            </div>
            <div class="a-grid-2">
                <div class="a-field">
                    <label for="smtp-user">Tài khoản SMTP</label>
                    <input id="smtp-user" name="smtp_username" type="text" autocomplete="off" value="<?= \App\escape($smtp_username) ?>">
                </div>
                <div class="a-field">
                    <label for="smtp-pass">Mật khẩu SMTP</label>
                    <input id="smtp-pass" name="smtp_password" type="password" autocomplete="new-password" placeholder="<?= $smtp_password !== '' ? '•••••••• (đã lưu)' : '' ?>">
                </div>
            </div>
            <div class="a-field">
                <label for="smtp-from">Email người gửi</label>
                <input id="smtp-from" name="smtp_from_email" type="email" value="<?= \App\escape($smtp_from_email) ?>">
                <span class="a-hint">Địa chỉ hiển thị trong mục "Từ" — để trống sẽ dùng tài khoản SMTP.</span>
            </div>
        </div>
        <div class="a-settings-actions" style="padding:0 1.4rem 1.2rem;">
            <?= $csrf->field() ?>
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Gửi thử email</h2>
            <p class="a-card-sub">Gửi một email kiểm tra qua máy chủ SMTP đã cấu hình.</p>
        </div>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/smtp/test') ?>">
        <div class="a-settings-body">
            <div class="a-field">
                <label for="test-to">Email người nhận</label>
                <input id="test-to" name="test_to" type="email" required placeholder="you@example.com">
            </div>
            <div class="a-field">
                <label for="test-subject">Tiêu đề</label>
                <input id="test-subject" name="test_subject" type="text" value="Email thử nghiệm từ UrlShortM">
            </div>
            <div class="a-field">
                <label for="test-body">Nội dung</label>
                <textarea id="test-body" name="test_body" rows="3">Email thử nghiệm từ UrlShortM. Nếu bạn nhận được email này, cấu hình SMTP hoạt động tốt.</textarea>
            </div>
        </div>
        <div class="a-settings-actions" style="padding:0 1.4rem 1.2rem;">
            <?= $csrf->field() ?>
            <button type="submit" class="a-btn a-btn-primary">Gửi thử</button>
        </div>
    </form>
</section>
