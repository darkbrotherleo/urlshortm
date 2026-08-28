<?php
/** @var array<int,string> $formats */
/** @var array<int,string> $allFormats */
/** @var bool $compress */
/** @var string $convert */
/** @var array<int,array<string,mixed>> $media */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã lưu cấu hình.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Cấu hình Media</h2>
            <p class="a-card-sub">Định dạng cho phép tải lên, nén và chuyển đổi ảnh tự động.</p>
        </div>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/media/save') ?>">
        <div class="a-settings-body">
            <div class="a-field">
                <label>Định dạng ảnh (tích chọn)</label>
                <div class="a-form-switch-grid a-form-switch-inline" style="padding-top:0.2rem;">
                    <?php foreach ($allFormats as $f): ?>
                        <label class="a-switch"><?= strtoupper($f) ?>
                            <input type="checkbox" name="format_<?= $f ?>" <?= in_array($f, $formats, true) ? 'checked' : '' ?>><span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="a-hint">Định dạng không chọn sẽ bị chặn khi tải ảnh lên.</span>
            </div>
            <div class="a-grid-2">
                <div class="a-field">
                    <label class="a-switch">Nén ảnh khi tải lên <input type="checkbox" name="media_compress" <?= $compress ? 'checked' : '' ?>><span></span></label>
                </div>
                <div class="a-field">
                    <label>Chuyển đổi ảnh khi tải lên</label>
                    <div class="a-form-switch-grid a-form-switch-inline">
                        <label class="a-switch">WebP <input type="radio" name="media_convert" value="webp" <?= $convert === 'webp' ? 'checked' : '' ?>><span></span></label>
                        <label class="a-switch">AVIF <input type="radio" name="media_convert" value="avif" <?= $convert === 'avif' ? 'checked' : '' ?>><span></span></label>
                        <label class="a-switch">Không <input type="radio" name="media_convert" value="" <?= $convert === '' ? 'checked' : '' ?>><span></span></label>
                    </div>
                </div>
            </div>
        </div>
        <div class="a-settings-actions" style="padding:0 1.4rem 1.2rem;">
            <?= $csrf->field() ?>
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Quản lý Media</h2>
            <p class="a-card-sub">Ảnh đã tải lên hệ thống.</p>
        </div>
        <form method="post" action="<?= \App\url_for('admin/settings/media/upload') ?>" enctype="multipart/form-data" class="dash-inline-form">
            <?= $csrf->field() ?>
            <input type="file" name="media_file" accept="image/*" required>
            <button type="submit" class="a-btn a-btn-primary">Tải lên</button>
        </form>
    </div>
    <div class="a-table-wrap">
        <table class="a-table a-table-compact">
            <thead><tr><th>Thumbnail (100x100)</th><th>Tên ảnh</th><th>URL ảnh</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($media === []): ?>
                    <tr><td colspan="4" class="dash-empty">Chưa có ảnh nào.</td></tr>
                <?php else: foreach ($media as $m): ?>
                    <tr>
                        <td><img class="a-media-thumb" src="<?= \App\url_for('/' . $m['path']) ?>" alt=""></td>
                        <td class="a-pixels"><?= \App\escape($m['original_name']) ?></td>
                        <td class="a-pixels"><code><?= \App\escape($m['path']) ?></code></td>
                        <td class="a-actions">
                            <form class="dash-inline-form" method="post" action="<?= \App\url_for('admin/settings/media/' . (int) $m['id'] . '/delete') ?>" onsubmit="return confirm('Xoá ảnh này?')"><?= $csrf->field() ?><button type="submit" class="a-btn a-btn-danger">Xoá</button></form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
