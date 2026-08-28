<?php
/** @var array<string,mixed>|null $plan */
/** @var \App\Security\Csrf $csrf */
$isEdit = $plan !== null;
$v = static function (string $key, mixed $default = '') use ($plan) {
    return $plan !== null && isset($plan[$key]) ? $plan[$key] : $default;
};
$checked = static function (mixed $value) {
    return (int) $value === 1 ? ' checked' : '';
};
?>
<section class="a-card">
    <div class="a-card-head">
        <h2><?= $isEdit ? 'Sửa gói dịch vụ' : 'Thêm gói dịch vụ' ?></h2>
        <a class="a-btn a-btn-soft" href="<?= \App\url_for('admin/packages') ?>">&#8592; Quay lại danh sách</a>
    </div>
    <form method="post" action="<?= \App\url_for($isEdit ? 'admin/packages/' . (int) $plan['id'] . '/update' : 'admin/packages/store') ?>" class="a-form" id="pkg-form">
        <?= $csrf->field() ?>

        <h3 class="a-form-sec">Thông tin chung</h3>
        <div class="a-form-row">
            <label for="pkg-name">Tên gói <span class="a-req">*</span></label>
            <input id="pkg-name" name="name" type="text" maxlength="100" required value="<?= \App\escape((string) $v('name')) ?>" placeholder="Ví dụ: Pro">
        </div>
        <div class="a-form-row">
            <label for="pkg-code">Slug (mã gói) <span class="a-req">*</span></label>
            <input id="pkg-code" name="code" type="text" maxlength="100" required value="<?= \App\escape((string) $v('code', 'goi-moi')) ?>" placeholder="pro" autocomplete="off">
        </div>
        <div class="a-form-row">
            <label for="pkg-desc">Mô tả</label>
            <textarea id="pkg-desc" name="description" rows="2" class="a-input"><?= \App\escape((string) $v('description')) ?></textarea>
        </div>

        <h3 class="a-form-sec">Giá &amp; chu kỳ</h3>
        <div class="a-form-row">
            <label for="pkg-price">Giá</label>
            <input id="pkg-price" name="price" type="number" min="0" step="0.01" value="<?= \App\escape((string) (float) $v('price', 0)) ?>">
        </div>
        <div class="a-form-row">
            <label for="pkg-currency">Đơn vị tiền</label>
            <input id="pkg-currency" name="currency" type="text" maxlength="10" value="<?= \App\escape((string) $v('currency', 'VND')) ?>">
        </div>
        <div class="a-form-row">
            <label for="pkg-period">Chu kỳ thanh toán</label>
            <select id="pkg-period" name="billing_period">
                <?php foreach (['monthly' => 'Theo tháng', 'yearly' => 'Theo năm', 'lifetime' => 'Trọn đời'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= (string) $v('billing_period', 'monthly') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <h3 class="a-form-sec">Giới hạn (nhập -1 = không giới hạn)</h3>
        <div class="a-form-grid">
            <div class="a-form-row"><label for="pkg-max-links">Số link tối đa</label><input id="pkg-max-links" name="max_links" type="number" min="-1" value="<?= (int) $v('max_links', 0) ?>"></div>
            <div class="a-form-row"><label for="pkg-max-clicks">Số click tối đa</label><input id="pkg-max-clicks" name="max_clicks" type="number" min="-1" value="<?= (int) $v('max_clicks', 0) ?>"></div>
            <div class="a-form-row"><label for="pkg-max-domains">Custom domain</label><input id="pkg-max-domains" name="max_custom_domains" type="number" min="-1" value="<?= (int) $v('max_custom_domains', 0) ?>"></div>
            <div class="a-form-row"><label for="pkg-max-pixels">Số Pixel</label><input id="pkg-max-pixels" name="max_pixels" type="number" min="-1" value="<?= (int) $v('max_pixels', 0) ?>"></div>
            <div class="a-form-row"><label for="pkg-max-users">Thành viên (team)</label><input id="pkg-max-users" name="max_users" type="number" min="1" value="<?= (int) $v('max_users', 1) ?>"></div>
            <div class="a-form-row"><label for="pkg-sort">Thứ tự hiển thị</label><input id="pkg-sort" name="sort_order" type="number" value="<?= (int) $v('sort_order', 0) ?>"></div>
        </div>

        <h3 class="a-form-sec">Tính năng</h3>
        <div class="a-form-switch-grid">
            <?php
            $flags = [
                'has_analytics' => 'Thống kê chi tiết',
                'has_qr_code' => 'QR Code',
                'has_password_protection' => 'Mật khẩu cho link',
                'has_link_expiration' => 'Thời hạn link',
                'has_utm_builder' => 'UTM Builder',
                'has_api_access' => 'Truy cập API',
            ];
            foreach ($flags as $key => $label):
            ?>
                <label class="a-switch"><?= $label ?>
                    <input type="checkbox" name="<?= $key ?>" <?= $checked($v($key, 0)) ?>><span></span>
                </label>
            <?php endforeach; ?>
        </div>

        <h3 class="a-form-sec">Trạng thái</h3>
        <div class="a-form-switch-grid">
            <label class="a-switch">Đang mở bán (Active)
                <input type="checkbox" name="is_active" <?= $checked($v('is_active', 1)) ?>><span></span>
            </label>
            <label class="a-switch">Hiển thị label "Được chọn nhiều"
                <input type="checkbox" name="is_popular" <?= $checked($v('is_popular', 0)) ?>><span></span>
            </label>
        </div>

        <div class="a-modal-actions">
            <a class="a-btn a-btn-soft" href="<?= \App\url_for('admin/packages') ?>">Huỷ</a>
            <button type="submit" class="a-btn a-btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo gói' ?></button>
        </div>
    </form>
</section>
