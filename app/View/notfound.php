<?php echo \App\render('header', ['title' => 'Không tìm thấy trang']); ?>
<section class="section">
    <div class="container error-page">
        <span class="pill">Rất tiếc</span>
        <h1 class="hero-title">Không tìm thấy link này.</h1>
        <p class="hero-sub">Có thể đường dẫn đã sai hoặc không còn tồn tại. Bạn thử quay lại trang chủ nhé.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= url_for('/') ?>">Về trang chủ</a>
        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
