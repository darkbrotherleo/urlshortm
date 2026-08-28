(function () {
    'use strict';

    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Form: loading state (chống gửi lặp) ---------- */
    var form = document.getElementById('shorten-form');
    var btn = document.getElementById('shorten-btn');

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Đang rút gọn…';
        });
    }

    /* ---------- Auth form: loading state ---------- */
    [['register-form', 'register-btn', 'Đang tạo tài khoản…'],
     ['login-form', 'login-btn', 'Đang đăng nhập…']].forEach(function (pair) {
        var f = document.getElementById(pair[0]);
        var b = document.getElementById(pair[1]);
        if (f && b) {
            f.addEventListener('submit', function () {
                b.disabled = true;
                b.textContent = pair[2];
            });
        }
    });

    /* ---------- Sao chép link (landing + dashboard) ---------- */
    var copyButtons = document.querySelectorAll('#copy-btn, .js-copy');

    function setCopyLabel(btn, text) {
        btn.textContent = text;
    }

    function attachCopy(btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copy');
            if (!url) return;

            function fallback() {
                var input = document.createElement('input');
                input.value = url;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                try {
                    document.execCommand('copy');
                    setCopyLabel(btn, 'Đã sao chép');
                } catch (e) {
                    setCopyLabel(btn, 'Không sao chép được');
                }
                document.body.removeChild(input);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function () {
                    setCopyLabel(btn, 'Đã sao chép');
                }, fallback);
            } else {
                fallback();
            }
        });
    }

    copyButtons.forEach(attachCopy);

    /* ---------- All Link: chọn hàng loạt + bulk ---------- */
    var table = document.getElementById('link-table');
    var checkAll = document.getElementById('check-all');
    var bulkForm = document.getElementById('bulk-form');

    if (table && bulkForm) {
        var rows = Array.prototype.slice.call(table.querySelectorAll('.row-check'));
        var bulkCount = document.getElementById('bulk-count');
        var bulkIds = document.getElementById('bulk-ids');

        function updateBulk() {
            var checked = rows.filter(function (r) { return r.checked; });
            var count = checked.length;
            if (bulkCount) bulkCount.textContent = String(count);
            if (bulkForm) bulkForm.hidden = count === 0;
            if (checkAll) checkAll.checked = count > 0 && count === rows.length;
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                rows.forEach(function (r) { r.checked = checkAll.checked; });
                updateBulk();
            });
        }

        rows.forEach(function (r) {
            r.addEventListener('change', updateBulk);
        });

        bulkForm.addEventListener('submit', function () {
            var ids = rows.filter(function (r) { return r.checked; }).map(function (r) { return r.value; });
            bulkIds.value = ids.join(',');
        });
    }

    /* ---------- Share: popup mạng xã hội ---------- */
    var shareMenu = document.getElementById('share-menu');

    function shareLinks(url, title) {
        var u = encodeURIComponent(url);
        var t = encodeURIComponent(title || '');
        return {
            fb: 'https://www.facebook.com/sharer/sharer.php?u=' + u,
            in: 'https://www.linkedin.com/sharing/share-offsite/?url=' + u,
            x: 'https://twitter.com/intent/tweet?url=' + u + '&text=' + t,
            msg: 'https://www.facebook.com/dialog/send?link=' + u + '&redirect_uri=' + u,
            zalo: 'https://zalo.me/share/url?url=' + u
        };
    }

    if (shareMenu) {
        var shareOpts = Array.prototype.slice.call(shareMenu.querySelectorAll('[data-share]'));

        document.querySelectorAll('.js-share').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();

                var url = btn.getAttribute('data-url') || '';
                var title = btn.getAttribute('data-title') || '';
                var links = shareLinks(url, title);

                shareOpts.forEach(function (a) {
                    a.href = links[a.getAttribute('data-share')];
                });

                var r = btn.getBoundingClientRect();
                shareMenu.hidden = false;
                shareMenu.style.top = (r.bottom + 6) + 'px';
                var left = r.left;
                if (left + shareMenu.offsetWidth > window.innerWidth - 8) {
                    left = window.innerWidth - shareMenu.offsetWidth - 8;
                }
                shareMenu.style.left = Math.max(8, left) + 'px';
            });
        });

        document.addEventListener('click', function (e) {
            if (shareMenu.hidden) return;
            if (!shareMenu.contains(e.target) && !e.target.closest('.js-share')) {
                shareMenu.hidden = true;
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !shareMenu.hidden) {
                shareMenu.hidden = true;
            }
        });
    }

    /* ---------- QR Code (xem assets/js/qr.js — QR Designer) ---------- */

    /* ---------- Link form: mật khẩu + slug preview ---------- */
    var linkForm = document.getElementById('link-form');
    if (linkForm) {
        var pwEnabled = document.getElementById('password_enabled');
        var pwField = document.getElementById('password_field');
        if (pwEnabled && pwField) {
            pwEnabled.addEventListener('change', function () {
                pwField.hidden = !pwEnabled.checked;
            });
        }

        var slugInput = document.getElementById('custom_slug');
        var slugPreview = document.getElementById('slug-preview');
        if (slugInput && slugPreview) {
            var base = slugPreview.getAttribute('data-base') || '';
            function renderPreview() {
                var v = slugInput.value.trim();
                slugPreview.textContent = v !== ''
                    ? base + '/' + v
                    : '';
            }
            slugInput.addEventListener('input', renderPreview);
            renderPreview();
        }

        /* ---------- Khung xem trước link (live) ---------- */
        var pvTitle = document.getElementById('link-preview-title');
        var pvDesc = document.getElementById('link-preview-desc');
        var pvUrl = document.getElementById('link-preview-url');
        var pvType = document.getElementById('link-preview-type');
        var pvThumb = document.getElementById('link-preview-thumb');
        var pvPlaceholder = document.getElementById('link-preview-placeholder');
        var pvBase = (slugPreview && slugPreview.getAttribute('data-base')) || '';
        var titleInput = document.getElementById('title');
        var descInput = document.getElementById('description');
        var typeInput = document.getElementById('link_type');
        var thumbInput = document.getElementById('thumbnail');

        function updatePreview() {
            if (pvTitle) {
                pvTitle.textContent = (titleInput && titleInput.value.trim()) || 'Link của bạn';
            }
            if (pvDesc) {
                pvDesc.textContent = (descInput && descInput.value.trim()) || 'Mô tả sẽ hiển thị tại đây.';
            }
            if (pvUrl) {
                var slug = (slugInput && slugInput.value.trim()) || '…';
                pvUrl.textContent = pvBase + '/' + slug;
            }
            if (pvType && typeInput) {
                var opt = typeInput.options[typeInput.selectedIndex];
                pvType.textContent = opt ? opt.text : '';
            }
        }

        if (titleInput) titleInput.addEventListener('input', updatePreview);
        if (descInput) descInput.addEventListener('input', updatePreview);
        if (slugInput) slugInput.addEventListener('input', updatePreview);
        if (typeInput) typeInput.addEventListener('change', updatePreview);

        if (thumbInput && pvThumb && pvPlaceholder) {
            thumbInput.addEventListener('change', function () {
                var file = thumbInput.files && thumbInput.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function (e) {
                    pvThumb.src = e.target.result;
                    pvThumb.hidden = false;
                    pvPlaceholder.hidden = true;
                };
                reader.readAsDataURL(file);
            });

            pvThumb.addEventListener('error', function () {
                pvThumb.hidden = true;
                pvPlaceholder.hidden = false;
            });
        }

        updatePreview();

        /* ---------- Pixel droplist: tick chọn nhiều ---------- */
        var pixelDrop = document.getElementById('pixel-drop');
        var pixelPanel = document.getElementById('pixel-panel');
        var pixelHidden = document.getElementById('pixels');
        if (pixelDrop && pixelPanel && pixelHidden) {
            var pixelChecks = Array.prototype.slice.call(pixelPanel.querySelectorAll('input[type="checkbox"]'));
            var pixelText = document.getElementById('pixel-drop-text');
            var pixelBadge = document.getElementById('pixel-drop-badge');

            function syncPixels() {
                var codes = pixelChecks.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
                pixelHidden.value = codes.join(', ');
                if (pixelBadge) {
                    pixelBadge.hidden = codes.length === 0;
                    pixelBadge.textContent = String(codes.length);
                }
                if (pixelText) {
                    pixelText.textContent = codes.length ? 'Đã chọn ' + codes.length + ' Pixel' : 'Chọn Pixel ID';
                }
            }

            pixelDrop.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = pixelPanel.hidden;
                pixelPanel.hidden = !open;
                pixelDrop.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            pixelChecks.forEach(function (c) {
                c.addEventListener('change', syncPixels);
            });

            document.addEventListener('click', function (e) {
                if (!pixelPanel.hidden && !pixelPanel.contains(e.target) && !pixelDrop.contains(e.target)) {
                    pixelPanel.hidden = true;
                    pixelDrop.setAttribute('aria-expanded', 'false');
                }
            });

            syncPixels();
        }

        /* ---------- UTM profile: tự điền nhanh ---------- */
        var utmProfile = document.getElementById('utm-profile');
        if (utmProfile) {
            utmProfile.addEventListener('change', function () {
                var opt = utmProfile.options[utmProfile.selectedIndex];
                if (!opt || !opt.value) return;
                var map = {
                    utm_campaign: opt.getAttribute('data-campaign'),
                    utm_medium: opt.getAttribute('data-medium'),
                    utm_source: opt.getAttribute('data-source'),
                    utm_term: opt.getAttribute('data-term'),
                    utm_content: opt.getAttribute('data-content')
                };
                Object.keys(map).forEach(function (field) {
                    var input = document.getElementById(field);
                    if (input) input.value = map[field] || '';
                });
            });
        }
    }

    /* ---------- Tracking panel: poll /stats/{slug} ---------- */
    var tracker = document.querySelector('.tool-track');
    if (tracker) {
        var statsUrl = tracker.getAttribute('data-stats-url');
        var countEl = document.getElementById('tracker-count');
        var failures = 0;
        var POLL_MS = 3000;
        var MAX_FAILURES = 6;

        function updateCount(value) {
            if (countEl) {
                countEl.textContent = String(value);
            }
        }

        function poll() {
            if (!statsUrl) return;

            fetch(statsUrl, {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('stats http ' + response.status);
                }
                return response.json();
            }).then(function (data) {
                failures = 0;
                if (data && typeof data.click_count === 'number') {
                    updateCount(data.click_count);
                }
                setTimeout(poll, POLL_MS);
            }).catch(function () {
                failures += 1;
                if (failures >= MAX_FAILURES) {
                    updateCount('—');
                    return;
                }
                setTimeout(poll, POLL_MS);
            });
        }

        poll();
    }

    /* ---------- Hiệu ứng nhẹ: reveal khi cuộn ---------- */
    if (!prefersReduced && 'IntersectionObserver' in window) {
        var revealEls = document.querySelectorAll('.reveal');

        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealEls.forEach(function (el) {
            revealObserver.observe(el);
        });
    }

    /* ---------- Menu drawer (nút bấm) ---------- */
    var menuBtn = document.getElementById('menu-btn');
    var menuPanel = document.getElementById('dash-menu');
    var menuOverlay = document.getElementById('dash-overlay');
    var menuClose = document.getElementById('menu-close');

    function setMenu(open) {
        if (!menuPanel || !menuOverlay) return;
        menuPanel.classList.toggle('is-open', open);
        menuOverlay.hidden = !open;
        if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', function () {
            setMenu(!menuPanel.classList.contains('is-open'));
        });
    }
    if (menuClose) {
        menuClose.addEventListener('click', function () { setMenu(false); });
    }
    if (menuOverlay) {
        menuOverlay.addEventListener('click', function () { setMenu(false); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMenu(false);
    });
    if (menuPanel) {
        menuPanel.querySelectorAll('.dash-nav-item, .dash-nav-sub').forEach(function (a) {
            a.addEventListener('click', function () { setMenu(false); });
        });
    }
})();
