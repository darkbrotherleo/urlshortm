</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div>
                <span class="brand">
                    <span class="brand-mark" aria-hidden="true"></span>
                    UrlShortM
                </span>
                <p class="footer-note">Rút gọn link dễ dàng, biết rõ ai đã bấm vào — nhẹ nhàng, miễn phí.</p>
            </div>
            <div class="footer-col">
                <h4>Khám phá</h4>
                <ul>
                    <li><a href="#tinh-nang">Tính năng</a></li>
                    <li><a href="#cach-hoat-dong">Cách hoạt động</a></li>
                    <li><a href="#cau-hoi">Câu hỏi thường gặp</a></li>
                    <li><a href="<?= url_for('tro-giup') ?>">Trợ giúp</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Dịch vụ</h4>
                <ul>
                    <li><a href="#cong-cu">Rút gọn link</a></li>
                    <li><a href="#theo-doi">Theo dõi lượt mở</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bar">
            <span>&copy; <?= date('Y') ?> UrlShortM — Dịch vụ rút gọn &amp; theo dõi link</span>
        </div>
    </div>
</footer>
<script src="<?= url_for('assets/js/app.js') ?>" defer></script>
<?= \App\site_seo_footer() ?>
</body>
</html>
