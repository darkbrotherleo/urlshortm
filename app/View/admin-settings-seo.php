<?php
/** @var string $siteName */
/** @var string $base */
/** @var string $robots_content */
/** @var array<string,string> $seo */
/** @var \App\Security\Csrf $csrf */
/** @var bool $ok */
/** @var string|null $error */
$v = static fn (string $k): string => (string) ($seo[$k] ?? '');
$tip = static fn (string $text): string => '<button type="button" class="seo-help" aria-label="Hướng dẫn" data-tip="' . \App\escape($text) . '">?</button>';
$robotsOptions = [
    'index, follow' => 'Index / Follow',
    'index, nofollow' => 'Index / Nofollow',
    'noindex, follow' => 'Noindex / Follow',
    'noindex, nofollow' => 'Noindex / Nofollow',
];
?>
<?php if ($ok): ?><div class="dash-flash" role="status">Đã lưu cấu hình.</div><?php endif; ?>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= \App\escape($error) ?></div><?php endif; ?>

<section class="a-card a-settings-card">
    <div class="a-card-head">
        <div class="a-card-title">
            <h2>SEO</h2>
            <p class="a-card-sub">Cấu hình SEO, Open Graph, xác minh, theo dõi và mã tuỳ chỉnh. Bấm dấu <span class="seo-help" style="cursor:default;" data-tip="">?</span> cạnh mỗi trường để xem hướng dẫn.</p>
        </div>
    </div>
    <form method="post" action="<?= \App\url_for('admin/settings/seo/save') ?>" id="seo-form">
        <?= $csrf->field() ?>
        <div class="a-table-wrap">
            <table class="a-table seo-table">
                <tbody>

                    <tr class="seo-section"><th colspan="2">SEO Cơ bản</th></tr>
                    <tr>
                        <td class="seo-name"><label for="seo-title">Site Title</label><?= $tip('Tên tiêu đề website — hiển thị trên tab trình duyệt và dòng tiêu đề trong kết quả tìm kiếm.') ?></td>
                        <td class="seo-value"><input id="seo-title" name="site_title" type="text" maxlength="190" value="<?= \App\escape($v('site_title')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-desc">Meta Description</label><?= $tip('Mô tả ngắn (khoảng 155 ký tự) hiển thị dưới tiêu đề trong kết quả tìm kiếm, giúp tăng tỷ lệ bấm.') ?></td>
                        <td class="seo-value"><textarea id="seo-desc" name="meta_description" rows="2"><?= \App\escape($v('meta_description')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-kw">Meta Keywords</label><?= $tip('Các từ khoá liên quan, cách nhau bởi dấu phẩy. Ít ảnh hưởng tới Google nhưng vẫn dùng cho một số công cụ khác.') ?></td>
                        <td class="seo-value"><input id="seo-kw" name="meta_keywords" type="text" maxlength="500" value="<?= \App\escape($v('meta_keywords')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-can">Canonical URL</label><?= $tip('URL chuẩn của trang để tránh bị đánh giá trùng nội dung. Để trống sẽ tự dùng Base URL hệ thống.') ?></td>
                        <td class="seo-value"><input id="seo-can" name="canonical_url" type="text" value="<?= \App\escape($v('canonical_url')) ?>" placeholder="<?= \App\escape($base) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-robots">Robots Meta</label><?= $tip('Chỉ dẫn cho công cụ tìm kiếm: index/nofollow... Noindex sẽ không đưa trang vào kết quả tìm kiếm.') ?></td>
                        <td class="seo-value">
                            <select id="seo-robots" name="robots_meta">
                                <option value="">Mặc định (index, follow)</option>
                                <?php foreach ($robotsOptions as $val => $label): ?>
                                    <option value="<?= \App\escape($val) ?>" <?= $v('robots_meta') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr class="seo-section"><th colspan="2">Social Media (Open Graph)</th></tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ogt">og:title</label><?= $tip('Tiêu đề hiển thị khi link được chia sẻ lên Facebook, Zalo, Messenger...') ?></td>
                        <td class="seo-value"><input id="seo-ogt" name="og_title" type="text" maxlength="190" value="<?= \App\escape($v('og_title')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ogd">og:description</label><?= $tip('Mô tả hiển thị khi chia sẻ lên mạng xã hội. Để trống sẽ lấy Meta Description.') ?></td>
                        <td class="seo-value"><textarea id="seo-ogd" name="og_description" rows="2"><?= \App\escape($v('og_description')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ogi">og:image</label><?= $tip('URL ảnh đại diện khi chia sẻ. Khuyến nghị 1200x630px để hiển thị đẹp trên Facebook.') ?></td>
                        <td class="seo-value"><input id="seo-ogi" name="og_image" type="text" value="<?= \App\escape($v('og_image')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ogtype">og:type</label><?= $tip('Loại nội dung khi chia sẻ: website, article, product...') ?></td>
                        <td class="seo-value"><input id="seo-ogtype" name="og_type" type="text" value="<?= \App\escape($v('og_type')) ?>" placeholder="website"></td>
                    </tr>
                    <tr>
                        <td class="seo-name">og:url<?= $tip('URL chuẩn khi chia sẻ — tự lấy từ Base URL hệ thống.') ?></td>
                        <td class="seo-value"><code><?= \App\escape($base) ?></code></td>
                    </tr>
                    <tr>
                        <td class="seo-name">og:site_name<?= $tip('Tên website khi chia sẻ — tự lấy từ Tên website trong Thông tin website.') ?></td>
                        <td class="seo-value"><code><?= \App\escape($siteName) ?></code></td>
                    </tr>

                    <tr class="seo-section"><th colspan="2">Verification</th></tr>
                    <tr>
                        <td class="seo-name"><label for="seo-gsc">Google Search Console</label><?= $tip('Mã xác minh Google: lấy phần content trong meta google-site-verification, dán vào đây.') ?></td>
                        <td class="seo-value"><input id="seo-gsc" name="gsc" type="text" value="<?= \App\escape($v('gsc')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-bing">Bing Webmaster Tools</label><?= $tip('Mã xác minh Bing: lấy phần content trong meta msvalidate.01.') ?></td>
                        <td class="seo-value"><input id="seo-bing" name="bing" type="text" value="<?= \App\escape($v('bing')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-yandex">Yandex Webmaster</label><?= $tip('Mã xác minh Yandex: lấy phần content trong meta yandex-verification.') ?></td>
                        <td class="seo-value"><input id="seo-yandex" name="yandex" type="text" value="<?= \App\escape($v('yandex')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-baidu">Baidu Webmaster</label><?= $tip('Mã xác minh Baidu: lấy phần content trong meta baidu-site-verification.') ?></td>
                        <td class="seo-value"><input id="seo-baidu" name="baidu" type="text" value="<?= \App\escape($v('baidu')) ?>"></td>
                    </tr>

                    <tr class="seo-section"><th colspan="2">Analytics &amp; Tracking</th></tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ga4">Google Analytics 4 (GA4)</label><?= $tip('Mã đo lường G-XXXXXXXX của Google Analytics 4. Thẻ tự động chèn vào head.') ?></td>
                        <td class="seo-value"><input id="seo-ga4" name="ga4" type="text" value="<?= \App\escape($v('ga4')) ?>" placeholder="G-XXXXXXXXXX"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-gtm">Google Tag Manager</label><?= $tip('Mã container GTM-XXXXXXX. Thẻ + noscript tự động chèn.') ?></td>
                        <td class="seo-value"><input id="seo-gtm" name="gtm" type="text" value="<?= \App\escape($v('gtm')) ?>" placeholder="GTM-XXXXXXX"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-mp">Meta Pixel (Facebook)</label><?= $tip('ID Pixel Facebook để theo dõi chuyển đổi từ quảng cáo.') ?></td>
                        <td class="seo-value"><input id="seo-mp" name="meta_pixel" type="text" value="<?= \App\escape($v('meta_pixel')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-tt">TikTok Pixel</label><?= $tip('ID Pixel TikTok để theo dõi chuyển đổi quảng cáo TikTok.') ?></td>
                        <td class="seo-value"><input id="seo-tt" name="tiktok_pixel" type="text" value="<?= \App\escape($v('tiktok_pixel')) ?>"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-ik">IndexNow Key</label><?= $tip('Key xác minh IndexNow giúp Bing/Yandex/Naver index nội dung nhanh.') ?></td>
                        <td class="seo-value"><input id="seo-ik" name="indexnow_key" type="text" value="<?= \App\escape($v('indexnow_key')) ?>"></td>
                    </tr>

                    <tr class="seo-section"><th colspan="2">Advanced / Custom Code</th></tr>
                    <tr>
                        <td class="seo-name">Sitemap URL (tự tạo)<?= $tip('Sitemap tự tạo chứa toàn bộ link ngắn + trang chính. Gửi lên Google Search Console để index nhanh.') ?></td>
                        <td class="seo-value"><a href="<?= \App\escape($base) ?>/sitemap.xml" target="_blank" rel="noopener"><code><?= \App\escape($base) ?>/sitemap.xml</code></a></td>
                    </tr>
                    <tr>
                        <td class="seo-name">Robots.txt (tự tạo)<?= $tip('File robots.txt tự tạo hướng dẫn bot tìm kiếm phần nào được phép thu thập.') ?></td>
                        <td class="seo-value"><a href="<?= \App\escape($base) ?>/robots.txt" target="_blank" rel="noopener"><code><?= \App\escape($base) ?>/robots.txt</code></a></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-robots-txt">Robots.txt nội dung</label><?= $tip('Nhập nội dung robots.txt bạn muốn. Mặc định điền sẵn nội dung hệ thống đã tạo — chỉnh sửa thoải mái, dòng Sitemap luôn được tự thêm.') ?></td>
                        <td class="seo-value"><textarea id="seo-robots-txt" name="robots_txt_content" class="a-code" rows="8"><?= \App\escape($robots_content) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-hreflang">Language / hreflang</label><?= $tip('Mã ngôn ngữ của trang (VD: vi-VN) — dùng cho SEO đa ngôn ngữ.') ?></td>
                        <td class="seo-value"><input id="seo-hreflang" name="hreflang" type="text" value="<?= \App\escape($v('hreflang')) ?>" placeholder="vi-VN"></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-head">Custom Head Code</label><?= $tip('Mã HTML/JS chèn vào <head> của mọi trang công khai.') ?></td>
                        <td class="seo-value"><textarea id="seo-head" name="head_code" class="a-code" rows="2"><?= \App\escape($v('head_code')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-body">Custom Body Code</label><?= $tip('Mã HTML/JS chèn ngay sau thẻ <body> của mọi trang công khai.') ?></td>
                        <td class="seo-value"><textarea id="seo-body" name="body_code" class="a-code" rows="2"><?= \App\escape($v('body_code')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name"><label for="seo-footer">Custom Footer Code</label><?= $tip('Mã HTML/JS chèn cuối trang (trước </body>) của mọi trang công khai.') ?></td>
                        <td class="seo-value"><textarea id="seo-footer" name="footer_code" class="a-code" rows="2"><?= \App\escape($v('footer_code')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td class="seo-name">AI Meta<?= $tip('Cho phép (hoặc chặn) chatbot AI như GPTBot, ClaudeBot đọc nội dung website.') ?></td>
                        <td class="seo-value"><label class="a-switch">Cho phép AI đọc website <input type="checkbox" name="ai_meta" <?= $v('ai_meta') === '1' ? 'checked' : '' ?>><span></span></label></td>
                    </tr>

                </tbody>
            </table>
        </div>
        <div class="a-settings-actions" style="padding:1rem 1.4rem 1.2rem;">
            <button type="submit" class="a-btn a-btn-primary">Lưu cấu hình</button>
        </div>
    </form>
</section>
