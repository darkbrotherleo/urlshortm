<?php
/**
 * @var string             $title
 * @var \App\Security\Csrf $csrf
 * @var string             $email
 * @var string|null        $error
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="auth">
    <div class="auth-blob" aria-hidden="true"></div>
    <div class="container auth-grid">
        <div class="auth-pitch">
            <span class="pill pill-light"><?= $error !== null ? 'Kích hoạt tài khoản' : 'Kiểm tra hộp thư của bạn' ?></span>
            <h1 class="auth-pitch-title"><?= $error !== null ? 'Liên kết kích hoạt không dùng được' : 'Một bước nữa là xong!' ?></h1>
            <p class="auth-pitch-sub">
                <?= $error !== null
                    ? $error
                    : 'Chúng tôi đã gửi email kích hoạt đến <strong>' . escape($email) . '</strong>. Bấm vào liên kết trong email để kích hoạt tài khoản.' ?>
            </p>
            <ul class="auth-list">
                <li><span aria-hidden="true">&#10003;</span> Không thấy email? Kiểm tra mục Spam / Quảng cáo</li>
                <li><span aria-hidden="true">&#10003;</span> Liên kết kích hoạt có hiệu lực trong 24 giờ</li>
                <li><span aria-hidden="true">&#10003;</span> Sau khi kích hoạt, tài khoản mới được đăng nhập</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2 class="auth-title"><?= $error !== null ? 'Kích hoạt tài khoản' : 'Kích hoạt tài khoản' ?></h2>
            <p class="auth-sub"><?= $error !== null ? 'Vui lòng thử lại hoặc liên hệ hỗ trợ.' : 'Bấm liên kết trong email để hoàn tất.' ?></p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($error === null): ?>
                <div class="alert alert-ok" role="status">Email kích hoạt đã được gửi. Hãy mở hộp thư <strong><?= escape($email) ?></strong> và bấm vào liên kết.</div>
            <?php endif; ?>

            <p class="auth-switch"><a href="<?= url_for('dang-nhap') ?>">Đăng nhập</a> · <a href="<?= url_for('dang-ky') ?>">Tạo tài khoản khác</a></p>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
