<?php
/** @var string $invoice_name */
/** @var string $invoice_tax_type */
/** @var string $invoice_address */
/** @var string $invoice_phone */
/** @var string $invoice_tax_id */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã lưu cấu hình.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Hoá đơn (người bán)</h2>
            <p class="a-card-sub">Thông tin doanh nghiệp / cá nhân xuất hiện trên hoá đơn GTGT.</p>
        </div>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/invoice/save') ?>">
        <div class="a-settings-body">
            <div class="a-field">
                <label for="inv-name">Tên doanh nghiệp / Họ và tên</label>
                <input id="inv-name" name="invoice_name" type="text" maxlength="190" value="<?= \App\escape($invoice_name) ?>">
            </div>
            <div class="a-grid-2">
                <div class="a-field">
                    <label for="inv-type">Loại doanh nghiệp</label>
                    <select id="inv-type" name="invoice_tax_type">
                        <option value="" <?= $invoice_tax_type === '' ? 'selected' : '' ?>>Chưa chọn</option>
                        <option value="individual" <?= $invoice_tax_type === 'individual' ? 'selected' : '' ?>>Cá nhân</option>
                        <option value="business" <?= $invoice_tax_type === 'business' ? 'selected' : '' ?>>Doanh nghiệp</option>
                    </select>
                </div>
                <div class="a-field">
                    <label for="inv-tax">Mã số thuế</label>
                    <input id="inv-tax" name="invoice_tax_id" type="text" maxlength="30" value="<?= \App\escape($invoice_tax_id) ?>" autocomplete="off">
                    <span class="a-hint">10–14 chữ số.</span>
                </div>
            </div>
            <div class="a-field">
                <label for="inv-addr">Địa chỉ</label>
                <input id="inv-addr" name="invoice_address" type="text" maxlength="255" value="<?= \App\escape($invoice_address) ?>">
            </div>
            <div class="a-grid-2">
                <div class="a-field">
                    <label for="inv-phone">Số điện thoại</label>
                    <input id="inv-phone" name="invoice_phone" type="tel" maxlength="20" value="<?= \App\escape($invoice_phone) ?>">
                </div>
            </div>
        </div>
        <div class="a-settings-actions" style="padding:0 1.4rem 1.2rem;">
            <?= $csrf->field() ?>
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>
