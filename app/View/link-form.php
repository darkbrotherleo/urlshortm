<?php
/**
 * Form tạo / sửa link.
 *
 * @var string                $title
 * @var string                $mode         'create' | 'edit'
 * @var array<string,mixed>   $values
 * @var array<int, array{id:int,name:string,total_links:int}> $folders
 * @var array<int, array{id:int,code:string,name:?string}> $pixels
 * @var array<int, array{id:int,domain:string,is_verified:int}> $domains
 * @var array<int, array{id:int,name:string,utm_campaign:?string,utm_medium:?string,utm_source:?string,utm_term:?string,utm_content:?string}> $utmProfiles
 * @var array<string,string>  $types
 * @var string|null           $error
 * @var array<string,mixed>|null $link
 * @var string                $base
 * @var \App\Security\Csrf    $csrf
 */
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? \App\url_for('dashboard/link/' . (int) ($link['id'] ?? 0) . '/update') : \App\url_for('dashboard/link');
$hasPassword = $isEdit && !empty($link['password_hash']);
$toggleOn = $hasPassword || $values['password_enabled'] === '1' || !empty($values['password']);
$selectedPixels = array_values(array_filter(array_map('trim', explode(',', (string) $values['pixels']))));
$currentThumb = (string) ($values['thumbnail'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= \App\escape($title) ?> — UrlShortM</title>
    <link rel="stylesheet" href="<?= \App\url_for('assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="dash-body">
<div class="lform-bar">
    <a class="dash-brand" href="<?= \App\url_for('/') ?>"><span class="brand-mark" aria-hidden="true"></span> UrlShortM</a>
    <div class="lform-bar-actions">
        <a class="btn btn-ghost btn-sm" href="<?= \App\url_for('dashboard') . '?tab=links' ?>">&#8592; Quay lại All Link</a>
        <form method="post" action="<?= \App\url_for('dang-xuat') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Thoát</button>
        </form>
    </div>
</div>

<main class="lform">
    <div class="lform-head">
        <p class="dash-crumb">// <?= $isEdit ? 'Chỉnh sửa' : 'Tạo mới' ?></p>
        <h1 class="dash-title"><?= $isEdit ? 'Chỉnh sửa link' : 'Tạo Link Mới' ?></h1>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-error" role="alert" aria-live="assertive"><?= \App\escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= \App\escape($actionUrl) ?>" class="lform-card" id="link-form" enctype="multipart/form-data">
        <?= $csrf->field() ?>

        <section class="lform-section lform-preview-section">
            <h2 class="lform-section-title">Xem trước link</h2>
            <div class="link-preview" id="link-preview">
                <div class="link-preview-media" id="link-preview-media">
                    <img
                        id="link-preview-thumb"
                        alt="Thumbnail link"
                        <?php if ($currentThumb !== ''): ?>src="<?= \App\escape(\App\url_for(ltrim($currentThumb, '/'))) ?>"<?php endif; ?>
                        <?= $currentThumb === '' ? 'hidden' : '' ?>
                    >
                    <span class="link-preview-placeholder" id="link-preview-placeholder" <?= $currentThumb === '' ? '' : 'hidden' ?> aria-hidden="true">
                        <span class="brand-mark"></span>
                        <small>Chưa có ảnh</small>
                    </span>
                </div>
                <div class="link-preview-body">
                    <span class="link-preview-type" id="link-preview-type"></span>
                    <h3 class="link-preview-title" id="link-preview-title"></h3>
                    <p class="link-preview-desc" id="link-preview-desc"></p>
                    <p class="link-preview-url" id="link-preview-url"></p>
                </div>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Loại link &amp; địa chỉ</h2>
            <div class="lform-grid">
                <div class="form-field">
                    <label for="link_type">Loại link</label>
                    <select id="link_type" name="link_type">
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= \App\escape($key) ?>" <?= $values['link_type'] === $key ? 'selected' : '' ?>><?= \App\escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="target">Địa chỉ / email / số điện thoại</label>
                    <input id="target" name="target" type="text" autocomplete="off" spellcheck="false"
                           value="<?= \App\escape((string) $values['target']) ?>" required
                           placeholder="https://example.com">
                </div>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Custom your link</h2>
            <div class="form-field">
                <label for="thumbnail">Thumbnail (kích thước gợi ý 1200x630)</label>
                <div class="thumb-field">
                    <?php if ($currentThumb !== ''): ?>
                        <img class="thumb-preview" src="<?= \App\url_for(ltrim($currentThumb, '/')) ?>" alt="Ảnh thumbnail hiện tại">
                    <?php endif; ?>
                    <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="lform-hint"><?= $currentThumb !== '' ? 'Chọn ảnh mới để thay thế.' : 'Tải ảnh lên (JPG, PNG, WEBP, GIF — tối đa 5MB).' ?></p>
                </div>
            </div>
            <div class="lform-grid">
                <div class="form-field">
                    <label for="title">Title của link</label>
                    <input id="title" name="title" type="text" maxlength="255" value="<?= \App\escape((string) $values['title']) ?>">
                </div>
            </div>
            <div class="form-field">
                <label for="description">Description của link</label>
                <textarea id="description" name="description" maxlength="500" rows="3"><?= \App\escape((string) $values['description']) ?></textarea>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Add Pixels ID</h2>
            <div class="form-field">
                <label id="pixel-drop-label">Pixels ID</label>
                <button type="button" id="pixel-drop" class="pixel-drop" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="pixel-drop-label">
                    <span id="pixel-drop-text">Chọn Pixel ID</span>
                    <span class="pixel-drop-badge" id="pixel-drop-badge" hidden>0</span>
                </button>
                <div class="pixel-panel" id="pixel-panel" hidden role="listbox" aria-multiselectable="true">
                    <?php if ($pixels === []): ?>
                        <p class="lform-hint">Chưa có Pixel nào. Sau khi thiết lập sẽ hiển thị tại đây.</p>
                    <?php else: ?>
                        <?php foreach ($pixels as $pixel): ?>
                            <label class="pixel-opt">
                                <input type="checkbox" value="<?= \App\escape($pixel['code']) ?>" <?= in_array($pixel['code'], $selectedPixels, true) ? 'checked' : '' ?>>
                                <span class="pixel-tick" aria-hidden="true"></span>
                                <span class="pixel-name"><?= \App\escape($pixel['name'] ?: $pixel['code']) ?></span>
                                <span class="pixel-code"><?= \App\escape($pixel['code']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="pixels" id="pixels" value="<?= \App\escape((string) $values['pixels']) ?>">
                <p class="lform-hint">Chọn bao nhiêu sẽ thêm bấy nhiêu Pixel ID cho link.</p>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Add UTM tags</h2>
            <div class="form-field">
                <label for="utm-profile">Dùng profile UTM có sẵn</label>
                <select id="utm-profile">
                    <option value="">— Chọn profile (tự điền nhanh) —</option>
                    <?php foreach ($utmProfiles as $profile): ?>
                        <option
                            value="<?= (int) $profile['id'] ?>"
                            data-campaign="<?= \App\escape((string) $profile['utm_campaign']) ?>"
                            data-medium="<?= \App\escape((string) $profile['utm_medium']) ?>"
                            data-source="<?= \App\escape((string) $profile['utm_source']) ?>"
                            data-term="<?= \App\escape((string) $profile['utm_term']) ?>"
                            data-content="<?= \App\escape((string) $profile['utm_content']) ?>"
                        ><?= \App\escape($profile['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lform-grid">
                <?php foreach (['utm_campaign' => 'UTM Campaign', 'utm_medium' => 'UTM Medium', 'utm_source' => 'UTM Source', 'utm_term' => 'UTM Term', 'utm_content' => 'UTM Content'] as $field => $label): ?>
                    <div class="form-field">
                        <label for="<?= $field ?>"><?= \App\escape($label) ?></label>
                        <input id="<?= $field ?>" name="<?= $field ?>" type="text" value="<?= \App\escape((string) $values[$field]) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Domain &amp; phần sau của link</h2>
            <div class="lform-grid">
                <div class="form-field">
                    <label for="domain">Choose domain name</label>
                    <select id="domain" name="domain">
                        <option value="">Local (mặc định)</option>
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?= \App\escape($domain['domain']) ?>" <?= $values['domain'] === $domain['domain'] ? 'selected' : '' ?>>
                                <?= \App\escape($domain['domain']) ?><?= $domain['is_verified'] ? '' : ' (chưa xác minh)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="lform-hint">Hiện tại dùng Local. Domain bạn thêm trong Cài đặt sẽ hiển thị tại đây.</p>
                </div>
                <div class="form-field">
                    <label for="custom_slug">Customize phần sau của link</label>
                    <input id="custom_slug" name="custom_slug" type="text" maxlength="16" value="<?= \App\escape((string) $values['custom_slug']) ?>"
                           placeholder="tu-chon (3-16 ký tự, để trống = tự sinh)">
                    <p class="lform-hint" id="slug-preview" data-base="<?= \App\escape($base) ?>"></p>
                </div>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Folder</h2>
            <div class="form-field">
                <label for="folder_id">Chọn thư mục</label>
                <select id="folder_id" name="folder_id">
                    <option value="">Không thư mục</option>
                    <?php foreach ($folders as $folder): ?>
                        <option value="<?= (int) $folder['id'] ?>" <?= (string) $values['folder_id'] === (string) $folder['id'] ? 'selected' : '' ?>><?= \App\escape($folder['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Bảo vệ link</h2>
            <input type="hidden" name="password_enabled" value="0">
            <label class="lform-toggle">
                <input type="checkbox" id="password_enabled" name="password_enabled" value="1" <?= $toggleOn ? 'checked' : '' ?>>
                <span class="lform-toggle-track" aria-hidden="true"></span>
                Bật mật khẩu cho link
            </label>
            <div class="form-field" id="password_field" <?= $toggleOn ? '' : 'hidden' ?>>
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="text" autocomplete="new-password" minlength="6"
                       value="" placeholder="Ít nhất 6 ký tự">
                <p class="lform-hint"><?= $hasPassword ? 'Bỏ trống để giữ mật khẩu hiện tại. Tắt nút bật/tắt để xoá mật khẩu.' : 'Nhập mật khẩu để bảo vệ link.' ?></p>
            </div>
        </section>

        <section class="lform-section">
            <h2 class="lform-section-title">Thời gian hoạt động</h2>
            <div class="lform-grid">
                <div class="form-field">
                    <label for="starts_at">Bắt đầu</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="<?= \App\escape((string) $values['starts_at']) ?>">
                </div>
                <div class="form-field">
                    <label for="ends_at">Kết thúc</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="<?= \App\escape((string) $values['ends_at']) ?>">
                </div>
            </div>
            <p class="lform-hint">Để trống cả hai = link hoạt động vô thời hạn.</p>
        </section>

        <div class="lform-actions">
            <a class="btn btn-ghost" href="<?= \App\url_for('dashboard') . '?tab=links' ?>">Huỷ</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo link' ?></button>
        </div>
    </form>
</main>
<script src="<?= \App\url_for('assets/js/app.js') ?>" defer></script>
</body>
</html>
