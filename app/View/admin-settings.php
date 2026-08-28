<?php
/** @var string $scheme */
/** @var string $host */
/** @var string $base */
/** @var string $basePath */
?>
<section class="a-card">
    <div class="a-card-head"><h2>Thông tin website</h2></div>
    <dl class="a-modal-info" style="padding:1.1rem 1.3rem;">
        <div><dt>Domain đang chạy</dt><dd><code><?= \App\escape($host) ?></code></dd></div>
        <div><dt>Giao thức</dt><dd><?= \App\escape($scheme) ?></dd></div>
        <div><dt>Base URL hệ thống</dt><dd><code><?= \App\escape($base) ?></code></dd></div>
        <div><dt>Đường dẫn cài đặt</dt><dd><code><?= \App\escape($basePath ?: '/') ?></code></dd></div>
    </dl>
    <p class="lform-hint" style="padding: 0.6rem 1.3rem 1rem;">
        Hệ thống <b>tự nhận diện domain đang chạy</b> để thực thi cho toàn bộ hệ thống
        (link rút gọn, trang, tài nguyên) — không cần cấu hình URL. Khi deploy lên hosting, mọi thứ tự chạy theo
        domain kết nối hosting; khi chạy local qua Laragon thì theo host bạn truy cập (VD: <code>urlshortm.test</code>).
    </p>
</section>
