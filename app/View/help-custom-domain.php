<?php
/**
 * @var string $title
 * @var string $relay_host
 */
echo \App\render('header', ['title' => $title]);
?>
<section class="section">
    <div class="container help-page">
        <span class="pill">Hướng dẫn</span>
        <h1 class="hero-title">Cách thêm tên miền tuỳ chỉnh</h1>
        <p class="hero-sub">
            Dùng tên miền riêng (thường là tên miền phụ như <code>link.viducongty.com</code>)
            để link ngắn của bạn hiển thị gọn gàng và chuyên nghiệp. Làm theo các bước dưới đây.
        </p>

        <div class="help-steps">
            <article class="help-step">
                <span class="help-step-num">1</span>
                <div>
                    <h2>Đăng nhập nhà cung cấp tên miền / hosting</h2>
                    <p>Vào tài khoản nơi bạn quản lý tên miền (nơi mua tên miền hoặc bảng quản trị hosting).</p>
                </div>
            </article>
            <article class="help-step">
                <span class="help-step-num">2</span>
                <div>
                    <h2>Mở cài đặt DNS của tên miền</h2>
                    <p>Trong phần quản lý tên miền, tìm mục <strong>DNS</strong> / <strong>Zone Editor</strong> / <strong>Bản ghi</strong>.</p>
                </div>
            </article>
            <article class="help-step">
                <span class="help-step-num">3</span>
                <div>
                    <h2>Tạo bản ghi CNAME trỏ về relay</h2>
                    <p>Thêm bản ghi <strong>CNAME</strong> cho tên miền phụ của bạn trỏ về
                        <code class="help-code"><?= \App\escape($relay_host) ?></code>.</p>
                    <p class="lform-hint">Ví dụ: <code>link</code> &rarr; CNAME &rarr; <code><?= \App\escape($relay_host) ?></code></p>
                </div>
            </article>
            <article class="help-step">
                <span class="help-step-num">4</span>
                <div>
                    <h2>Chờ quá trình lan truyền DNS</h2>
                    <p>Bản ghi DNS có thể mất vài phút đến vài giờ để lan truyền toàn cầu.</p>
                </div>
            </article>
            <article class="help-step">
                <span class="help-step-num">5</span>
                <div>
                    <h2>Thêm tên miền phụ vào bảng điều khiển</h2>
                    <p>Vào <strong>Cài đặt &#8594; Custom domain</strong>, thêm tên miền phụ của bạn
                        (vd <code>link.viducongty.com</code>) và bấm <strong>Xác minh</strong>.</p>
                </div>
            </article>
        </div>

        <aside class="dash-note help-note">
            <strong>Xác minh bằng TXT:</strong> bên cạnh CNAME, bạn cũng có thể thêm bản ghi
            <strong>TXT</strong> (giá trị hiển thị tại trang Custom domain) để hệ thống xác nhận
            quyền sở hữu. <code>localhost</code>, <code>127.0.0.1</code> và domain kết thúc
            bằng <code>.test</code>/<code>.localhost</code> được tự xác minh để thử nghiệm.
            Nội dung tham khảo từ trung tâm hỗ trợ Switchy (bài "Custom your own subdomain"),
            được biên dịch sang tiếng Việt.
        </aside>
    </div>
</section>
<?php echo \App\render('footer'); ?>
