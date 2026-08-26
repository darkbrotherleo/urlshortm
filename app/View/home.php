<?php
/**
 * Landing page — soft & friendly service (Lexend, không ngôn ngữ kỹ thuật).
 *
 * @var string                $title
 * @var \App\Security\Csrf    $csrf
 * @var string                $target
 * @var array|null            $result
 * @var string|null           $error
 * @var string|null           $status
 * @var string|null           $statusMessage
 */
echo \App\render('header', ['title' => $title]);
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-blob hero-blob-a" aria-hidden="true"></div>
    <div class="hero-blob hero-blob-b" aria-hidden="true"></div>

    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="pill">Rút gọn link &middot; Theo dõi lượt mở</span>
            <h1 class="hero-title">
                Link dài lê thê,<br>
                <span class="hero-accent">giờ gọn trong vài giây.</span>
            </h1>
            <p class="hero-sub">
                Dán đường dẫn vào ô bên cạnh, chúng tôi biến nó thành một link
                ngắn gọn, dễ chia sẻ. Kèm theo đó, bạn luôn biết có bao nhiêu
                người đã bấm vào.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#cong-cu">Rút gọn link của bạn</a>
                <a class="btn btn-ghost" href="#cach-hoat-dong">Xem cách hoạt động</a>
            </div>
            <p class="hero-note">Miễn phí &middot; Không cần đăng ký &middot; Xong trong vài giây</p>
        </div>

        <div class="hero-device">
            <section id="cong-cu" class="tool-card" aria-label="Công cụ rút gọn link">
                <div class="tool-head">
                    <span class="tool-title">Rút gọn ngay tại đây</span>
                    <span class="tool-status"><span class="pulse-dot" aria-hidden="true"></span> sẵn sàng</span>
                </div>
                <div class="tool-body">
                    <?php if ($status === 'error' && $error !== null): ?>
                        <div class="alert alert-error" role="alert" aria-live="assertive">
                            <?= escape($error) ?>
                        </div>
                    <?php endif; ?>

                    <form id="shorten-form" method="post" action="<?= url_for('shorten') ?>" novalidate>
                        <?= $csrf->field() ?>
                        <label class="visually-hidden" for="target">Địa chỉ cần rút gọn</label>
                        <div class="tool-input">
                            <input
                                id="target"
                                name="target"
                                type="text"
                                inputmode="url"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Dán đường dẫn của bạn vào đây…"
                                value="<?= escape($target) ?>"
                                maxlength="2048"
                                required
                            >
                            <button id="shorten-btn" type="submit" class="btn btn-primary">Rút gọn</button>
                        </div>
                    </form>

                    <?php if ($status === 'success' && $result !== null): ?>
                        <?php $shortUrl = url_for($result['slug']); ?>
                        <div class="tool-result" aria-live="polite">
                            <p class="tool-ok">Link của bạn đã sẵn sàng!</p>
                            <div class="tool-link-row">
                                <a class="tool-link" href="<?= url_for($result['slug']) ?>"><?= escape($shortUrl) ?></a>
                                <button id="copy-btn" type="button" class="btn btn-soft" data-copy="<?= escape($shortUrl) ?>">Sao chép</button>
                            </div>
                            <div
                                class="tool-track"
                                data-slug="<?= escape($result['slug']) ?>"
                                data-stats-url="<?= url_for('stats/' . $result['slug']) ?>"
                            >
                                <span>Lượt mở:</span>
                                <strong class="tool-count" id="tracker-count" aria-live="polite"><?= (int) $result['click_count'] ?></strong>
                                <small>Mở link ở tab khác, con số sẽ tự nhảy lên.</small>
                            </div>
                            <a class="btn btn-soft btn-block" href="<?= url_for($result['slug']) ?>">Mở link thử xem &rarr;</a>
                        </div>
                    <?php else: ?>
                        <p class="tool-hint">Chưa có gì ở đây. Dán một đường dẫn phía trên là xong.</p>
                    <?php endif; ?>
                </div>
            </section>
            <p class="device-note">Đây là công cụ thật — dùng được ngay, không phải hình minh hoạ.</p>
        </div>
    </div>
</section>

<!-- TÍNH NĂNG -->
<section id="tinh-nang" class="section">
    <div class="container">
        <h2 class="section-title">Vì sao mọi người thích dùng?</h2>
        <p class="section-sub">Ba điều nhỏ, làm cuộc sống của đường dẫn dài trở nên đỡ phiền hơn nhiều.</p>
        <div class="cards">
            <article class="card reveal">
                <span class="card-icon" aria-hidden="true">&#9872;</span>
                <h3>Gọn gàng, dễ nhớ</h3>
                <p>Một đường dẫn chỉ còn vài ký tự, dễ đọc, dễ gõ, dễ dán vào bất kỳ đâu — tin nhắn, bài đăng hay cả tờ rơi.</p>
            </article>
            <article class="card reveal">
                <span class="card-icon" aria-hidden="true">&#128200;</span>
                <h3>Biết rõ lượt mở</h3>
                <p>Không còn đoán mò. Mỗi khi có người bấm vào link, con số được cập nhật ngay trên trang, nhìn là thấy.</p>
            </article>
            <article class="card reveal">
                <span class="card-icon" aria-hidden="true">&#10004;</span>
                <h3>Dùng là xong</h3>
                <p>Chẳng cần tài khoản, chẳng cần cài đặt. Dán link, bấm rút gọn, chia sẻ — vậy là xong.</p>
            </article>
        </div>
    </div>
</section>

<!-- CÁCH HOẠT ĐỘNG -->
<section id="cach-hoat-dong" class="section section-alt">
    <div class="container">
        <h2 class="section-title">Chỉ ba bước, đơn giản không ngờ</h2>
        <p class="section-sub">Không có gì phức tạp. Ngay cả lần đầu dùng, bạn cũng sẽ xong trong chưa đầy một phút.</p>
        <div class="steps">
            <article class="step reveal">
                <span class="step-num">1</span>
                <h3>Dán đường dẫn</h3>
                <p>Bạn copy link dài từ bất kỳ đâu, dán vào ô trên đầu trang. Thiếu phần đầu của địa chỉ cũng không sao.</p>
            </article>
            <article class="step reveal">
                <span class="step-num">2</span>
                <h3>Nhận link gọn gàng</h3>
                <p>Chỉ vài giây sau, bạn có một link ngắn sạch sẽ để gửi cho bạn bè, đồng nghiệp hay khách hàng.</p>
            </article>
            <article class="step reveal">
                <span class="step-num">3</span>
                <h3>Xem ai quan tâm</h3>
                <p>Mỗi lần có người bấm, lượt mở tăng lên một. Con số đó nói lên sức hút của link bạn.</p>
            </article>
        </div>
    </div>
</section>

<!-- THEO DÕI -->
<section id="theo-doi" class="section">
    <div class="container compare">
        <h2 class="section-title">Trước &middot; Sau</h2>
        <div class="compare-grid">
            <div class="compare-card compare-before reveal">
                <h3>Trước kia</h3>
                <p>Bạn đăng một đường dẫn dài lê thê lên khắp nơi, rồi… chẳng biết có ai bấm vào hay không.</p>
            </div>
            <div class="compare-card compare-after reveal">
                <h3>Bây giờ</h3>
                <p>Link ngắn gọn, đẹp mắt. Mở lần nào là đếm lần đó — con số tăng lên ngay khi có người quan tâm.</p>
            </div>
        </div>
    </div>
</section>

<!-- CÂU HỎI -->
<section id="cau-hoi" class="section section-alt">
    <div class="container faq">
        <h2 class="section-title">Những câu hay hỏi nhất</h2>
        <details class="faq-item">
            <summary>Link ngắn có bị mất hay hết hạn không?</summary>
            <p>Không. Link của bạn được giữ ổn định và dùng được lâu dài.</p>
        </details>
        <details class="faq-item">
            <summary>Tôi có phải đăng ký tài khoản không?</summary>
            <p>Không cần. Cứ dán link, bấm rút gọn và chia sẻ là xong.</p>
        </details>
        <details class="faq-item">
            <summary>Làm sao biết được có bao nhiêu người mở link của mình?</summary>
            <p>Ngay sau khi rút gọn, ô theo dõi hiện ra ngay bên cạnh. Mỗi lượt mở đều được cộng vào đó.</p>
        </details>
        <details class="faq-item">
            <summary>Nếu tôi dán nhầm một địa chỉ thì sao?</summary>
            <p>Đừng lo. Nếu địa chỉ không hợp lệ, chúng tôi sẽ nhắc nhẹ nhàng và bạn chỉ cần dán lại cho đúng.</p>
        </details>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="cta-blob" aria-hidden="true"></div>
    <div class="container cta-inner">
        <h2>Có một đường dẫn dài đang chờ bạn rút gọn.</h2>
        <a class="btn btn-light" href="#cong-cu">Bắt đầu ngay</a>
    </div>
</section>

<?php echo \App\render('footer'); ?>
