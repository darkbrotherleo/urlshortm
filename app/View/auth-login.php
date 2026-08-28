<?php
/**
 * @var string                $title
 * @var \App\Security\Csrf    $csrf
 * @var array{email:string}   $values
 * @var string|null           $error
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="auth">
    <div class="auth-blob" aria-hidden="true"></div>
    <div class="container auth-grid">
        <div class="auth-pitch">
            <span class="pill pill-light">Chào bạn quay lại</span>
            <h1 class="auth-pitch-title">Link của bạn<br>đang chờ ở đây.</h1>
            <p class="auth-pitch-sub">
                Đăng nhập để xem các link đã rút gọn, theo dõi lượt mở
                và tiếp tục đúng nơi bạn dừng lại.
            </p>
            <ul class="auth-list">
                <li><span aria-hidden="true">&#10003;</span> Quản lý link gọn gàng trong một tài khoản</li>
                <li><span aria-hidden="true">&#10003;</span> Số liệu lượt mở luôn cập nhật</li>
                <li><span aria-hidden="true">&#10003;</span> An toàn — mật khẩu được mã hoá an toàn</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2 class="auth-title">Đăng nhập</h2>
            <p class="auth-sub">Nhập email và mật khẩu của bạn.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= escape($error) ?></div>
            <?php endif; ?>

            <form id="login-form" method="post" action="<?= url_for('dang-nhap') ?>" novalidate>
                <?= $csrf->field() ?>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required placeholder="ban@vidu.com" value="<?= escape($values['email']) ?>">
                </div>
                <div class="form-field">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Mật khẩu của bạn">
                </div>
                <button id="login-btn" type="submit" class="btn btn-primary btn-block btn-lg">Đăng nhập</button>
            </form>

            <p class="auth-switch"><a href="<?= url_for('quen-mat-khau') ?>">Quên mật khẩu?</a> · Chưa có tài khoản? <a href="<?= url_for('dang-ky') ?>">Đăng ký ngay</a></p>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
