<?php
/**
 * @var string                $title
 * @var \App\Security\Csrf    $csrf
 * @var array{name:string,email:string} $values
 * @var string|null           $error
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="auth">
    <div class="auth-blob" aria-hidden="true"></div>
    <div class="container auth-grid">
        <div class="auth-pitch">
            <span class="pill pill-light">Tạo tài khoản miễn phí</span>
            <h1 class="auth-pitch-title">Một tài khoản,<br>gọn tất cả link của bạn.</h1>
            <p class="auth-pitch-sub">
                Theo dõi lượt mở, gom mọi link đã tạo về một chỗ, và sẵn sàng
                nâng cấp gói khi bạn cần nhiều hơn.
            </p>
            <ul class="auth-list">
                <li><span aria-hidden="true">&#10003;</span> Link của bạn được gắn về tài khoản — không lạc lõng</li>
                <li><span aria-hidden="true">&#10003;</span> Lượt mở theo dõi trực tiếp, nhìn là thấy</li>
                <li><span aria-hidden="true">&#10003;</span> Bắt đầu miễn phí, nâng cấp bất cứ lúc nào</li>
            </ul>
            <p class="auth-trust">Cam kết không bán dữ liệu. Chúng tôi dùng email của bạn để giữ liên lạc về tài khoản thôi.</p>
        </div>

        <div class="auth-card">
            <h2 class="auth-title">Tạo tài khoản</h2>
            <p class="auth-sub">Điền vài thông tin dưới đây là xong.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive"><?= escape($error) ?></div>
            <?php endif; ?>

            <form id="register-form" method="post" action="<?= url_for('dang-ky') ?>" novalidate>
                <?= $csrf->field() ?>
                <div class="form-field">
                    <label for="name">Tên hiển thị <span class="opt">(không bắt buộc)</span></label>
                    <input id="name" name="name" type="text" autocomplete="name" maxlength="100" placeholder="Ví dụ: Minh Anh" value="<?= escape($values['name']) ?>">
                </div>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required placeholder="ban@vidu.com" value="<?= escape($values['email']) ?>">
                </div>
                <div class="form-field">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="Ít nhất 8 ký tự">
                </div>
                <div class="form-field">
                    <label for="password_confirm">Nhập lại mật khẩu</label>
                    <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" required placeholder="Nhập lại mật khẩu">
                </div>
                <button id="register-btn" type="submit" class="btn btn-primary btn-block btn-lg">Tạo tài khoản</button>
            </form>

            <p class="auth-switch">Đã có tài khoản? <a href="<?= url_for('dang-nhap') ?>">Đăng nhập</a></p>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
