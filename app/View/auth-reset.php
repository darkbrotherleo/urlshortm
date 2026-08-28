<?php
/**
 * @var string             $title
 * @var \App\Security\Csrf $csrf
 * @var string             $token
 * @var string|null        $error
 * @var bool               $done
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="auth">
    <div class="auth-blob" aria-hidden="true"></div>
    <div class="container auth-grid">
        <div class="auth-pitch">
            <span class="pill pill-light">Đặt lại mật khẩu</span>
            <h1 class="auth-pitch-title">Tạo mật khẩu mới.</h1>
            <p class="auth-pitch-sub">
                Liên kết đặt lại có hiệu lực trong 30 phút. Mật khẩu mới cần ít nhất 8 ký tự.
            </p>
            <ul class="auth-list">
                <li><span aria-hidden="true">&#10003;</span> Chỉ thực hiện qua liên kết gửi trong email</li>
                <li><span aria-hidden="true">&#10003;</span> Sau khi đổi, bạn đăng nhập bằng mật khẩu mới</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2 class="auth-title">Đặt lại mật khẩu</h2>
            <p class="auth-sub"><?= $done ? 'Hoàn tất!' : 'Nhập mật khẩu mới.' ?></p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= escape($error) ?></div>
            <?php endif; ?>
            <?php if ($done): ?>
                <div class="alert alert-ok" role="status">Mật khẩu đã được đổi thành công. Bạn có thể <a href="<?= url_for('dang-nhap') ?>">đăng nhập</a> ngay.</div>
            <?php elseif ($token !== ''): ?>
                <form method="post" action="<?= url_for('dat-lai-mat-khau') ?>" novalidate>
                    <?= $csrf->field() ?>
                    <input type="hidden" name="token" value="<?= escape($token) ?>">
                    <div class="form-field">
                        <label for="password">Mật khẩu mới</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="Ít nhất 8 ký tự">
                    </div>
                    <div class="form-field">
                        <label for="password_confirm">Nhập lại mật khẩu mới</label>
                        <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" required placeholder="Nhập lại mật khẩu">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Đặt lại mật khẩu</button>
                </form>
            <?php else: ?>
                <p class="auth-switch"><a href="<?= url_for('quen-mat-khau') ?>">Gửi lại liên kết</a></p>
            <?php endif; ?>

            <p class="auth-switch">Nhớ mật khẩu? <a href="<?= url_for('dang-nhap') ?>">Đăng nhập</a></p>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
