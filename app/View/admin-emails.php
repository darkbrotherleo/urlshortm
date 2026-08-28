<?php
/** @var array<string,array{label:string,html:string}> $previews */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
/** @var bool $configured */
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã gửi email thử nghiệm.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>Email Template</h2>
            <p class="a-card-sub">Khung thiết kế email + 5 template có sẵn. Xem trước hoặc gửi thử từng mẫu.</p>
        </div>
        <span class="a-pill <?= $configured ? 'ok' : 'warn' ?>"><?= $configured ? 'SMTP đã cấu hình' : 'SMTP chưa cấu hình' ?></span>
    </div>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th style="width:220px;">Template</th><th>Xem trước</th><th>Gửi thử</th></tr></thead>
            <tbody>
                <?php foreach ($previews as $key => $p): ?>
                    <tr>
                        <td class="a-pixels" style="font-weight:700;"><?= \App\escape($p['label']) ?><small class="a-sub-text"><?= \App\escape($key) ?></small></td>
                        <td><button type="button" class="a-btn a-btn-soft js-email-preview" data-type="<?= \App\escape($key) ?>">Xem trước</button></td>
                        <td>
                            <form method="post" action="<?= \App\url_for('admin/emails/send') ?>" class="dash-inline-form">
                                <?= $csrf->field() ?>
                                <input type="hidden" name="template" value="<?= \App\escape($key) ?>">
                                <input type="email" name="test_to" placeholder="email người nhận..." required style="padding:0.45rem 0.7rem;font-size:0.8rem;border:1px solid var(--aline);border-radius:8px;min-width:200px;">
                                <button type="submit" class="a-btn a-btn-primary">Gửi thử</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script id="admin-emails-data" type="application/json"><?= json_encode($previews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="a-modal" id="a-modal-email" hidden>
    <div class="a-modal-card a-modal-card-lg" role="dialog" aria-modal="true" aria-label="Xem trước email">
        <div class="a-card-head"><h2 id="a-email-title">Xem trước email</h2><button type="button" class="a-modal-close" data-close="a-modal-email" aria-label="Đóng">&#10005;</button></div>
        <div style="padding:1rem;background:#F1F5F9;">
            <iframe id="a-email-frame" style="width:100%;height:70vh;border:1px solid var(--aline);border-radius:10px;background:#fff;" sandbox=""></iframe>
        </div>
        <div class="a-modal-actions"><button type="button" class="a-btn a-btn-soft" data-close="a-modal-email">Đóng</button></div>
    </div>
</div>
