<?php
/** @var string $scheme */
/** @var string $host */
/** @var string $base */
/** @var string $path */
/** @var string $server */
/** @var string $db */
/** @var bool $dbOk */
$rows = [
    'Tên miền' => '<code>' . \App\escape($host) . '</code>',
    'Giao thức' => \App\escape($scheme),
    'Base URL hệ thống' => '<code>' . \App\escape($base) . '</code>',
    'Đường dẫn cài đặt' => '<code>' . \App\escape($path) . '</code>',
    'Máy chủ' => \App\escape($server),
    'Database' => \App\escape($db),
];
?>
<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Thông tin hệ thống</h2>
            <p class="a-card-sub">Hệ thống tự nhận diện môi trường đang chạy — chỉ xem, không chỉnh sửa.</p>
        </div>
        <span class="a-badge">Chỉ xem</span>
        <span class="a-pill <?= $dbOk ? 'ok' : 'bad' ?>"><?= $dbOk ? 'Hoạt động' : 'Lỗi kết nối DB' ?></span>
    </div>
    <div class="a-table-wrap">
        <table class="a-table a-table-compact">
            <tbody>
                <?php foreach ($rows as $label => $value): ?>
                    <tr><th style="width:230px;color:var(--amuted);font-weight:600;"><?= \App\escape($label) ?></th><td><?= $value ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
