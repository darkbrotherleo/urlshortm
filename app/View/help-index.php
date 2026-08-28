<?php
/** @var string $title */
echo \App\render('header', ['title' => $title]);
?>
<section class="section">
    <div class="container help-page">
        <span class="pill">Wiki tài liệu</span>
        <h1 class="hero-title">Wiki tài liệu UrlShortM</h1>
        <p class="hero-sub">
            Tài liệu hướng dẫn từng bước cho mọi tính năng. Bấm vào từng mục để xem
            nội dung chi tiết.
        </p>

        <nav class="wiki-toc" aria-label="Mục lục">
            <span>Mục lục</span>
            <?php
            $toc = [
                ['tao-tai-khoan', 'Tạo tài khoản &amp; đăng nhập'],
                ['tao-link', 'Tạo link rút gọn'],
                ['bao-ve', 'Bảo vệ link bằng mật khẩu'],
                ['thoi-gian', 'Đặt thời gian hoạt động'],
                ['theo-doi', 'Theo dõi lượt mở'],
                ['qr', 'Thiết kế QR Code &amp; tải file'],
                ['chia-se', 'Chia sẻ link'],
                ['folder', 'Quản lý link bằng Folder'],
                ['utm', 'Sử dụng UTM tags'],
                ['domain', 'Thêm tên miền tuỳ chỉnh'],
                ['pixel', 'Cách lấy Pixel ID'],
                ['thiet-lap-pixel', 'Thiết lập Pixels'],
                ['faq', 'Câu hỏi thường gặp'],
            ];
            foreach ($toc as $item): ?>
                <a href="#<?= $item[0] ?>"><?= $item[1] ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="wiki-docs">

            <details class="wiki-article" id="tao-tai-khoan">
                <summary>Tạo tài khoản &amp; đăng nhập</summary>
                <div class="wiki-body">
                    <h3>Tạo tài khoản</h3>
                    <ol>
                        <li>Bấm nút <strong>Đăng ký</strong> trên trang chủ (góc trên bên phải).</li>
                        <li>Điền <strong>Tên hiển thị</strong> (không bắt buộc), <strong>Email</strong>, <strong>Mật khẩu</strong> (ít nhất 8 ký tự) và <strong>Nhập lại mật khẩu</strong>.</li>
                        <li>Bấm <strong>Tạo tài khoản</strong>. Hệ thống tự đăng nhập và đưa bạn vào bảng điều khiển.</li>
                    </ol>
                    <h3>Đăng nhập</h3>
                    <ol>
                        <li>Bấm <strong>Đăng nhập</strong>, nhập email và mật khẩu.</li>
                        <li>Thành công sẽ chuyển tới <strong>Bảng điều khiển</strong>.</li>
                    </ol>
                    <p class="wiki-note">Bảng điều khiển có sidebar: Tổng quan, Quản lý link (All Link, Folder), Tài khoản và Cài đặt.</p>
                </div>
            </details>

            <details class="wiki-article" id="tao-link">
                <summary>Tạo link rút gọn</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Vào <strong>All Link</strong> rồi bấm <strong>+ Tạo Link Mới</strong>.</li>
                        <li><strong>Loại link:</strong> chọn Link, Email, WhatsApp, Phone, Sms, Telegram, Skype, Line, Wechat, Viber, Messenger hoặc vCard.</li>
                        <li><strong>Địa chỉ:</strong> nhập URL, email hoặc số điện thoại tuỳ loại link.</li>
                        <li>Tuỳ chọn: <strong>Title</strong>, <strong>Description</strong>, <strong>Thumbnail</strong> (tải ảnh lên), <strong>Pixels</strong> (tick chọn), <strong>UTM tags</strong>.</li>
                        <li><strong>Phần sau của link:</strong> để trống hệ thống tự sinh; hoặc tự đặt 3-16 ký tự (a-z, 0-9, gạch ngang, gạch dưới).</li>
                        <li>Chọn <strong>Folder</strong>, <strong>Domain</strong> (nếu có), bật <strong>Mật khẩu</strong> hoặc đặt <strong>thời gian</strong> nếu cần.</li>
                        <li>Xem trước link ở khung bên trên, rồi bấm <strong>Tạo link</strong>.</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="bao-ve">
                <summary>Bảo vệ link bằng mật khẩu</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Khi tạo hoặc sửa link, bật <strong>Bật mật khẩu cho link</strong>.</li>
                        <li>Nhập <strong>Mật khẩu</strong> (ít nhất 6 ký tự).</li>
                        <li>Người mở link sẽ được yêu cầu nhập mật khẩu trước khi chuyển tới đích.</li>
                        <li>Tắt công tắc để <strong>xoá mật khẩu</strong>; bỏ trống khi đang sửa = giữ nguyên mật khẩu cũ.</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="thoi-gian">
                <summary>Đặt thời gian hoạt động</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Trong form tạo/sửa link, đặt <strong>Bắt đầu</strong> và <strong>Kết thúc</strong> (ngày giờ).</li>
                        <li>Để trống cả hai = link hoạt động vô thời hạn.</li>
                        <li>Ngoài khung thời gian, link hiển thị thông báo <em>chưa được mở</em> hoặc <em>đã hết hạn</em>.</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="theo-doi">
                <summary>Theo dõi lượt mở</summary>
                <div class="wiki-body">
                    <ul>
                        <li>Cột <strong>Clicks</strong> trong bảng All Link hiển thị số lượt mở thật của từng link.</li>
                        <li>Mỗi lần có người mở link ngắn, con số tăng đúng 1.</li>
                        <li>Số liệu tổng (link, lượt mở) nằm ở tab <strong>Tổng quan</strong>.</li>
                    </ul>
                </div>
            </details>

            <details class="wiki-article" id="qr">
                <summary>Thiết kế QR Code &amp; tải file</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Trong bảng All Link, bấm nút <strong>QR</strong> ở cột Action.</li>
                        <li>Chọn <strong>Shape style</strong> (Square, Rounded, Dots, Extra-rounded) cho các chấm bên trong.</li>
                        <li>Chọn <strong>Corner style</strong> cho 3 điểm chính, rồi chọn màu cho Shape và Corner.</li>
                        <li>Xem trước cập nhật trực tiếp ở khung bên phải.</li>
                        <li>Bấm <strong>Tải về file SVG</strong> hoặc <strong>Tải về file PNG</strong>.</li>
                    </ol>
                    <p class="wiki-note">&#128161; Luôn quét mã QR bằng điện thoại trước khi in để kiểm tra khả năng đọc.</p>
                </div>
            </details>

            <details class="wiki-article" id="chia-se">
                <summary>Chia sẻ link</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Trong bảng All Link, bấm nút <strong>Share</strong>.</li>
                        <li>Chọn mạng xã hội: Facebook, LinkedIn, X, Messenger hoặc Zalo.</li>
                        <li>Trang mạng đó sẽ mở khung chia sẻ với link ngắn và tiêu đề đã tự điền.</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="folder">
                <summary>Quản lý link bằng Folder</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Vào tab <strong>Folder</strong> (nhóm Quản lý link).</li>
                        <li>Tạo thư mục bằng ô nhập tên, hoặc dùng ô <strong>Chuyển thư mục</strong> trong bảng để gán link.</li>
                        <li>Bấm vào một thư mục để xem các link bên trong; xoá thư mục sẽ đưa link về "Không thư mục".</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="utm">
                <summary>Sử dụng UTM tags</summary>
                <div class="wiki-body">
                    <ul>
                        <li>Khi tạo link, mục <strong>Add UTM tags</strong> gồm Campaign, Medium, Source, Term, Content.</li>
                        <li>Để điền nhanh: tạo <strong>profile UTM</strong> trong Cài đặt → UTMs tracking, rồi chọn profile khi tạo link.</li>
                        <li>UTM giúp đo hiệu quả chiến dịch trong Google Analytics.</li>
                    </ul>
                </div>
            </details>

            <details class="wiki-article" id="domain">
                <summary>Thêm tên miền tuỳ chỉnh</summary>
                <div class="wiki-body">
                    <p>Dùng tên miền riêng (vd <code>link.viducongty.com</code>) cho link ngắn của bạn:</p>
                    <ol>
                        <li>Đăng nhập nhà cung cấp tên miền / hosting, mở cài đặt DNS.</li>
                        <li>Tạo bản ghi CNAME trỏ về relay của hệ thống.</li>
                        <li>Chờ lan truyền DNS.</li>
                        <li>Trong Cài đặt → Custom domain, thêm tên miền phụ và bấm Xác minh.</li>
                    </ol>
                    <p><a href="<?= \App\url_for('tro-giup/custom-domain') ?>" target="_blank" rel="noopener">Xem bài viết chi tiết: Cách thêm tên miền tuỳ chỉnh &rarr;</a></p>
                </div>
            </details>

            <details class="wiki-article" id="pixel">
                <summary>Cách lấy Pixel ID</summary>
                <div class="wiki-body">
                    <p>Mỗi nền tảng quảng cáo cấp một mã Pixel riêng. Hướng dẫn tìm &amp; copy mã cho
                    Facebook/Meta, Instagram, Google Ads, Google Analytics 4, Google Tag Manager, TikTok,
                    Zalo, Pinterest, Snapchat có trong bài viết chi tiết.</p>
                    <p><a href="<?= \App\url_for('tro-giup/pixel-id') ?>" target="_blank" rel="noopener">Xem bài viết: Cách lấy Pixel ID của từng nền tảng &rarr;</a></p>
                </div>
            </details>

            <details class="wiki-article" id="thiet-lap-pixel">
                <summary>Thiết lập Pixels</summary>
                <div class="wiki-body">
                    <ol>
                        <li>Vào Cài đặt → <strong>Thiết lập Pixels</strong>.</li>
                        <li>Bấm tạo Pixel: chọn <strong>Platform</strong>, nhập <strong>Name of Pixel</strong> và <strong>Pixel ID</strong>.</li>
                        <li>Pixel xuất hiện trong droplist khi tạo/sửa link — tick chọn bao nhiêu thì thêm bấy nhiêu.</li>
                        <li>Dùng nút <strong>Sửa / Xoá</strong> để quản lý pixel của bạn.</li>
                    </ol>
                </div>
            </details>

            <details class="wiki-article" id="faq">
                <summary>Câu hỏi thường gặp</summary>
                <div class="wiki-body">
                    <h3>Link ngắn có hết hạn không?</h3>
                    <p>Không, trừ khi bạn đặt thời gian hoạt động (bắt đầu/kết thúc) cho link.</p>
                    <h3>Tôi có cần tài khoản không?</h3>
                    <p>Không — ai cũng rút gọn link ngay trên trang chủ. Có tài khoản để quản lý và theo dõi link của mình.</p>
                    <h3>Làm sao biết link được mở bao nhiêu lần?</h3>
                    <p>Xem cột Clicks trong bảng All Link.</p>
                    <h3>Làm sao xoá link?</h3>
                    <p>Trong bảng All Link, bấm Delete ở cột Action (hoặc tick nhiều dòng rồi "Xoá đã chọn").</p>
                    <h3>Mật khẩu tài khoản có đổi được không?</h3>
                    <p>Chưa có chức năng đổi mật khẩu — sẽ bổ sung trong bản cập nhật tới.</p>
                </div>
            </details>

        </div>
    </div>
</section>
<?php echo \App\render('footer'); ?>
