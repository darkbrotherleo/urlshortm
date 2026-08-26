<?php
/** @var string $message */
echo \App\render('header', ['title' => 'Đang có trục trặc nhỏ']); ?>
<section class="section">
    <div class="container error-page">
        <span class="pill">Có chút trục trặc</span>
        <h1 class="hero-title">Ơ, hình như có gì đó chưa ổn.</h1>
        <div class="alert alert-error" role="alert"><?= escape($message) ?></div>
        <p class="hero-sub">Bạn thử lại sau một chút nhé. Chúng tôi đang xem lại.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= url_for('/') ?>">Về trang chủ</a>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
