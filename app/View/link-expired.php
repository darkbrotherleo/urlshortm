<?php
/** @var string $message */
echo \App\render('header', ['title' => 'Link không mở được']); ?>
<section class="section">
    <div class="container error-page">
        <span class="pill">Rất tiếc</span>
        <h1 class="hero-title">Link này không mở được.</h1>
        <p class="hero-sub"><?= \App\escape($message) ?></p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= \App\url_for('/') ?>">Về trang chủ</a>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
