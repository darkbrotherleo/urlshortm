<?php
/** @var array<int,array<string,mixed>> $users */
/** @var array<int,array{id:int,code:string,name:string,price_monthly:?string}> $plans */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$planOptions = '<option value="0">Miễn phí</option>';
foreach ($plans as $p) {
    if (($p['code'] ?? '') === 'free') {
        continue; // "Miễn phí" đã có option value 0
    }
    $planOptions .= '<option value="' . (int) $p['id'] . '">' . \App\escape($p['name']) . '</option>';
}
$rows = '';
foreach ($users as $u) {
    $planName = (string) ($u['plan_name'] ?? '');
    $isPaid = $planName !== '' && $planName !== 'Miễn phí';
    $planCell = $isPaid
        ? '<span class="a-badge">' . \App\escape($planName) . '</span>'
        : '<span class="a-badge muted">Miễn phí</span>';
    $rows .= '<tr>'
        . '<td class="a-pixels"><button type="button" class="a-user-link js-admin-user" data-id="' . (int) $u['id'] . '">' . \App\escape($u['username']) . '</button></td>'
        . '<td class="a-pixels">' . \App\escape($u['email']) . '</td>'
        . '<td>' . $planCell . '</td>'
        . '<td class="a-date">' . \App\escape(!empty($u['starts_at']) ? substr((string) $u['starts_at'], 0, 10) : '—') . '</td>'
        . '<td class="a-date">' . \App\escape(!empty($u['ends_at']) ? substr((string) $u['ends_at'], 0, 10) : '—') . '</td>'
        . '<td>' . (($u['status'] ?? '') === 'active' ? '<span class="a-pill ok">Hoạt động</span>' : (($u['status'] ?? '') === 'pending' ? '<span class="a-pill warn">Chờ kích hoạt</span>' : '<span class="a-pill bad">Bị vô hiệu</span>')) . '</td>'
        . '</tr>';
}
?>
<?php if ($ok): ?>
    <div class="dash-flash" role="status">Đã lưu thay đổi.</div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div>
<?php endif; ?>

<section class="a-card">
    <div class="a-card-head">
        <h2>Danh sách người dùng</h2>
        <div class="a-toolbar-inline">
            <input type="search" id="a-user-search" class="a-input" placeholder="Tìm username / email..." autocomplete="off">
        </div>
    </div>
    <div class="a-table-wrap">
        <table class="a-table" id="a-user-table">
            <thead><tr><th>Username</th><th>Email</th><th>Gói</th><th>Ngày mua</th><th>Ngày hết hạn</th><th>Trạng thái</th></tr></thead>
            <tbody><?= $rows ?></tbody>
        </table>
    </div>
</section>

<script id="admin-users-data" type="application/json"><?= json_encode($users, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="a-modal" id="a-modal-info" hidden>
    <div class="a-modal-card" role="dialog" aria-modal="true" aria-label="Thông tin người dùng">
        <div class="a-card-head"><h2>Thông tin người dùng</h2><button type="button" class="a-modal-close" data-close="a-modal-info" aria-label="Đóng">&#10005;</button></div>
        <dl class="a-modal-info" id="a-modal-info-body"></dl>
        <div class="a-modal-actions">
            <button type="button" class="a-btn a-btn-soft" data-close="a-modal-info">Đóng</button>
            <button type="button" class="a-btn a-btn-primary" id="a-info-edit">Sửa</button>
        </div>
    </div>
</div>

<div class="a-modal" id="a-modal-edit" hidden>
    <div class="a-modal-card a-modal-card-lg" role="dialog" aria-modal="true" aria-label="Sửa người dùng">
        <div class="a-card-head"><h2>Sửa người dùng</h2><button type="button" class="a-modal-close" data-close="a-modal-edit" aria-label="Đóng">&#10005;</button></div>
        <form method="post" action="<?= \App\url_for('admin/users/update') ?>" class="a-form">
            <?= $csrf->field() ?>
            <input type="hidden" name="user_id" id="ae-user-id" value="">
            <div class="a-form-row"><label>Tên hiển thị</label><input name="display_name" id="ae-display" type="text" maxlength="100"></div>
            <div class="a-form-row"><label>Email</label><input name="email" id="ae-email" type="email" maxlength="191"></div>
            <div class="a-form-row"><label>Số điện thoại</label><input name="phone" id="ae-phone" type="tel" maxlength="20"></div>
            <div class="a-form-row"><label>Địa chỉ</label><input name="address" id="ae-address" type="text" maxlength="255"></div>
            <div class="a-form-row"><label>Tỉnh / Thành phố</label><input name="city" id="ae-city" type="text" maxlength="100"></div>
            <div class="a-form-row"><label>Loại khách hàng</label>
                <select name="tax_type" id="ae-tax-type">
                    <option value="">Chưa chọn</option>
                    <option value="individual">Cá nhân</option>
                    <option value="business">Doanh nghiệp</option>
                </select>
            </div>
            <div class="a-form-row"><label>Tên công ty / đơn vị</label><input name="company_name" id="ae-company" type="text" maxlength="190"></div>
            <div class="a-form-row"><label>Mã số thuế</label><input name="tax_id" id="ae-tax-id" type="text" maxlength="30"></div>
            <div class="a-form-row"><label>Tên trên hoá đơn</label><input name="invoice_name" id="ae-invoice" type="text" maxlength="190"></div>
            <div class="a-form-row"><label>Gói dịch vụ</label>
                <select name="plan_id" id="ae-plan"><?= $planOptions ?></select>
            </div>
            <div class="a-form-row"><label>Ngày mua</label><input name="sub_start" id="ae-sub-start" type="date"></div>
            <div class="a-form-row"><label>Ngày hết hạn</label><input name="sub_end" id="ae-sub-end" type="date"></div>
            <div class="a-form-row"><label>Trạng thái</label>
                <select name="status" id="ae-status">
                    <option value="active">Hoạt động</option>
                    <option value="disabled">Vô hiệu hoá</option>
                </select>
            </div>
            <div class="a-modal-actions">
                <button type="button" class="a-btn a-btn-soft" data-close="a-modal-edit">Huỷ</button>
                <button type="submit" class="a-btn a-btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
