<?php
/** @var array{client_id:string,secret:string,mode:string} $paypal */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
?>
<?php if ($ok): ?>
    <div class="dash-flash" role="status">Đã lưu cấu hình cổng thanh toán.</div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div>
<?php endif; ?>

<section class="a-card">
    <div class="a-card-head"><h2>PayPal</h2><span class="a-badge"><?= $paypal['mode'] === 'live' ? 'Live' : 'Sandbox' ?></span></div>
    <form method="post" action="<?= \App\url_for('admin/payments/save') ?>" class="a-form" id="paypal-form">
        <?= $csrf->field() ?>
        <p class="lform-hint" style="padding: 0.2rem 0 0.8rem;">
            Nhập thông tin PayPal Checkout. Dùng <b>sandbox</b> để kiểm tra luồng (cần tài khoản
            <a href="https://developer.paypal.com/dashboard/" target="_blank" rel="noopener">PayPal Developer</a>).
            Nếu để trống, hệ thống chạy <b>chế độ giả lập</b> để test toàn bộ luồng mua - kích hoạt gói.
        </p>
        <div class="a-form-row"><label for="pp-client">Client ID</label><input id="pp-client" name="paypal_client_id" type="text" value="<?= \App\escape($paypal['client_id']) ?>" autocomplete="off"></div>
        <div class="a-form-row"><label for="pp-secret">Secret</label><input id="pp-secret" name="paypal_secret" type="password" placeholder="<?= $paypal['secret'] !== '' ? '•••••••• (đã lưu, để trống nếu giữ nguyên)' : 'Chưa cấu hình' ?>" autocomplete="off"></div>
        <div class="a-form-row"><label for="pp-mode">Môi trường</label>
            <select id="pp-mode" name="paypal_mode">
                <option value="sandbox" <?= $paypal['mode'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (kiểm tra)</option>
                <option value="live" <?= $paypal['mode'] === 'live' ? 'selected' : '' ?>>Live (sản xuất)</option>
            </select>
        </div>
        <div class="a-modal-actions" style="border-top:none;padding:0.4rem 0 0;">
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>
