/* Admin: biá»ƒu Ä‘á»“ Tá»•ng quan + modal (user/Ä‘Æ¡n hÃ ng) + tÃ¬m kiáº¿m + auto slug. */
(function () {
    'use strict';

    /* ---------- ÄÃ³ng modal (toÃ n cá»¥c â€” cháº¡y má»i trang) ---------- */
    function hide(id) { var el = document.getElementById(id); if (el) el.hidden = true; }
    function show(id) { var el = document.getElementById(id); if (el) el.hidden = false; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { hide(btn.getAttribute('data-close')); });
    });
    [].forEach.call(document.querySelectorAll('.a-modal'), function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) hide(m.id); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            [].forEach.call(document.querySelectorAll('.a-modal:not([hidden])'), function (m) { hide(m.id); });
        }
    });

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Biá»ƒu Ä‘á»“ Tá»•ng quan ---------- */
    var dataEl = document.getElementById('admin-chart-data');
    if (dataEl && window.Chart) {
        var d;
        try { d = JSON.parse(dataEl.textContent || '{}'); } catch (e) { d = null; }
        if (d) {
            var colors = ['#6366F1', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9'];
            function mk(id, type, labels, values) {
                var el = document.getElementById(id);
                if (!el) return;
                var dataset = { data: values, backgroundColor: colors, borderColor: colors[0], fill: true, tension: 0.3 };
                if (type === 'line') dataset.borderColor = colors[0]; else dataset.backgroundColor = colors;
                if (type === 'pie') { dataset.borderColor = '#fff'; dataset.borderWidth = 2; }
                new Chart(el.getContext('2d'), {
                    type: type,
                    data: { labels: labels || [], datasets: [dataset] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: type === 'pie' ? { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } : { display: false } }
                    }
                });
            }
            var days = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN', 'T2', 'T3', 'T4'];
            var weeks = ['Tuáº§n 1', 'Tuáº§n 2', 'Tuáº§n 3', 'Tuáº§n 4', 'Tuáº§n 5', 'Tuáº§n 6', 'Tuáº§n 7', 'Tuáº§n 8', 'Tuáº§n 9', 'Tuáº§n 10'];
            mk('chart-users-new', 'line', weeks, d.users);
            mk('chart-links-created', 'bar', weeks, d.links);
            mk('chart-clicks-day', 'line', days, d.clicks);
            mk('chart-revenue-plan', 'pie', (d.revenue || []).map(function (r) { return r.label; }), (d.revenue || []).map(function (r) { return r.value; }));
        }
    }

    /* ---------- Quáº£n lÃ½ ngÆ°á»i dÃ¹ng ---------- */
    var usersEl = document.getElementById('admin-users-data');
    if (usersEl) {
        var users = {};
        try {
            JSON.parse(usersEl.textContent || '[]').forEach(function (u) { users[u.id] = u; });
        } catch (e) { /* noop */ }

        function planLabel(u) {
            return (u.plan_name && u.plan_name !== 'Miá»…n phÃ­') ? u.plan_name : 'Miá»…n phÃ­';
        }
        function datePart(v) {
            return v ? String(v).slice(0, 10) : 'â€”';
        }

        function openInfo(id) {
            var u = users[id];
            if (!u) return;
            var rows = [
                ['Username', u.username],
                ['Email', u.email],
                ['Sá»‘ Ä‘iá»‡n thoáº¡i', u.phone],
                ['Äá»‹a chá»‰', u.address ? u.address + (u.city ? ', ' + u.city : '') : (u.city || 'â€”')],
                ['Loáº¡i khÃ¡ch hÃ ng', u.tax_type === 'business' ? 'Doanh nghiá»‡p' : (u.tax_type === 'individual' ? 'CÃ¡ nhÃ¢n' : 'â€”')],
                ['CÃ´ng ty', u.company_name],
                ['MÃ£ sá»‘ thuáº¿', u.tax_id],
                ['GÃ³i', planLabel(u)],
                ['NgÃ y mua', datePart(u.starts_at)],
                ['NgÃ y háº¿t háº¡n', datePart(u.ends_at)],
                ['Tráº¡ng thÃ¡i', u.status === 'active' ? 'Hoáº¡t Ä‘á»™ng' : 'Bá»‹ vÃ´ hiá»‡u hoÃ¡'],
                ['Tham gia', datePart(u.created_at)]
            ];
            var html = '';
            rows.forEach(function (r) {
                html += '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
            });
            document.getElementById('a-modal-info-body').innerHTML = html;
            document.getElementById('a-info-edit').setAttribute('data-id', id);
            show('a-modal-info');
        }

        function openEdit(id) {
            var u = users[id];
            if (!u) return;
            document.getElementById('ae-user-id').value = id;
            document.getElementById('ae-display').value = u.display_name || u.username || '';
            document.getElementById('ae-email').value = u.email || '';
            document.getElementById('ae-phone').value = u.phone || '';
            document.getElementById('ae-address').value = u.address || '';
            document.getElementById('ae-city').value = u.city || '';
            document.getElementById('ae-tax-type').value = u.tax_type || '';
            document.getElementById('ae-company').value = u.company_name || '';
            document.getElementById('ae-tax-id').value = u.tax_id || '';
            document.getElementById('ae-invoice').value = u.invoice_name || '';
            document.getElementById('ae-plan').value = u.plan_id ? String(u.plan_id) : '0';
            document.getElementById('ae-sub-start').value = datePart(u.starts_at) !== 'â€”' ? datePart(u.starts_at) : '';
            document.getElementById('ae-sub-end').value = datePart(u.ends_at) !== 'â€”' ? datePart(u.ends_at) : '';
            document.getElementById('ae-status').value = (u.status === 'active') ? 'active' : 'disabled';
            show('a-modal-edit');
        }

        document.querySelectorAll('.js-admin-user').forEach(function (btn) {
            btn.addEventListener('click', function () { openInfo(parseInt(btn.getAttribute('data-id'), 10)); });
        });
        document.querySelectorAll('.a-user-edit').forEach(function (btn) {
            btn.addEventListener('click', function () { openEdit(parseInt(btn.getAttribute('data-id'), 10)); });
        });
        var infoEdit = document.getElementById('a-info-edit');
        if (infoEdit) {
            infoEdit.addEventListener('click', function () {
                hide('a-modal-info');
                openEdit(parseInt(infoEdit.getAttribute('data-id'), 10));
            });
        }

        var search = document.getElementById('a-user-search');
        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                document.querySelectorAll('#a-user-table tbody tr').forEach(function (tr) {
                    tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1;
                });
            });
        }
    }

    /* ---------- Chi tiáº¿t Ä‘Æ¡n hÃ ng ---------- */
    var ordersEl = document.getElementById('admin-orders-data');
    if (ordersEl) {
        var orders = {};
        try {
            JSON.parse(ordersEl.textContent || '[]').forEach(function (o) { orders[o.id] = o; });
        } catch (e) { /* noop */ }
        var labels = { pending: 'Chá» thanh toÃ¡n', paid: 'ÄÃ£ thanh toÃ¡n', canceled: 'ÄÃ£ huá»·', failed: 'Tháº¥t báº¡i' };
        function price(v, c) {
            return Number(v || 0).toLocaleString('vi-VN') + ' ' + (c || 'VND');
        }
        document.querySelectorAll('.js-order-view').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var o = orders[parseInt(btn.getAttribute('data-id'), 10)];
                if (!o) return;
                var rows = [
                    ['MÃ£ Ä‘Æ¡n', o.order_code],
                    ['KhÃ¡ch hÃ ng', o.username],
                    ['Email', o.user_email],
                    ['GÃ³i', o.plan_name],
                    ['Chu ká»³', o.billing_period],
                    ['Sá»‘ tiá»n', price(o.amount, o.currency)],
                    ['PhÆ°Æ¡ng thá»©c', o.payment_method],
                    ['Tráº¡ng thÃ¡i', labels[o.status] || o.status],
                    ['Gateway ID', o.gateway_order_id || 'â€”'],
                    ['NgÆ°á»i thanh toÃ¡n', o.payer || 'â€”'],
                    ['NgÃ y táº¡o', o.created_at],
                    ['NgÃ y thanh toÃ¡n', o.paid_at || 'â€”']
                ];
                var html = '';
                rows.forEach(function (r) {
                    html += '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
                });
                document.getElementById('a-modal-order-body').innerHTML = html;
                show('a-modal-order');
            });
        });
    }

    /* ---------- Auto slug tá»« tÃªn gÃ³i ---------- */
    var nameEl = document.getElementById('pkg-name');
    var codeEl = document.getElementById('pkg-code');
    if (nameEl && codeEl) {
        var map = { 'Ã ':'a','Ã¡':'a','áº£':'a','Ã£':'a','áº¡':'a','Äƒ':'a','áº±':'a','áº¯':'a','áº³':'a','áºµ':'a','áº·':'a','Ã¢':'a','áº§':'a','áº¥':'a','áº©':'a','áº«':'a','áº­':'a','Ä‘':'d','Ã¨':'e','Ã©':'e','áº»':'e','áº½':'e','áº¹':'e','Ãª':'e','á»':'e','áº¿':'e','á»ƒ':'e','á»…':'e','á»‡':'e','Ã¬':'i','Ã­':'i','á»‰':'i','Ä©':'i','á»‹':'i','Ã²':'o','Ã³':'o','á»':'o','Ãµ':'o','á»':'o','Ã´':'o','á»“':'o','á»‘':'o','á»•':'o','á»—':'o','á»™':'o','Æ¡':'o','á»':'o','á»›':'o','á»Ÿ':'o','á»¡':'o','á»£':'o','Ã¹':'u','Ãº':'u','á»§':'u','Å©':'u','á»¥':'u','Æ°':'u','á»«':'u','á»©':'u','á»­':'u','á»¯':'u','á»±':'u','á»³':'y','Ã½':'y','á»·':'y','á»¹':'y','á»µ':'y' };
        function slugify(s) {
            s = s.toLowerCase().split('').map(function (c) { return map[c] || c; }).join('');
            return s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 100);
        }
        nameEl.addEventListener('input', function () {
            if (codeEl.value === '' || codeEl.getAttribute('data-auto') === '1') {
                codeEl.value = slugify(nameEl.value);
                codeEl.setAttribute('data-auto', '1');
            }
        });
        codeEl.addEventListener('input', function () { codeEl.setAttribute('data-auto', '0'); });
    }
})();

/* Admin links: modal S?a link */
(function () {
    'use strict';
    var el = document.getElementById('admin-links-data');
    if (!el) return;
    var links = {};
    try { JSON.parse(el.textContent || '[]').forEach(function (l) { links[l.id] = l; }); } catch (e) { return; }
    document.querySelectorAll('.js-link-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var l = links[parseInt(btn.getAttribute('data-id'), 10)];
            if (!l) return;
            document.getElementById('link-edit-form').action = btn.getAttribute('data-action') || ('admin/links/' + l.id + '/update');
            document.getElementById('le-slug').textContent = l.slug;
            document.getElementById('le-target').value = l.target_url || '';
            document.getElementById('le-title').value = l.title || '';
            document.getElementById('le-desc').value = l.description || '';
            document.getElementById('le-ends').value = (l.ends_at || '').slice(0, 10);
            document.getElementById('le-active').checked = (l.is_active == 1);
            document.getElementById('a-modal-link').hidden = false;
        });
    });
})();

/* Admin vouchers: modal tạo / sửa */
(function () {
    'use strict';
    var el = document.getElementById('admin-vouchers-data');
    var createBtn = document.getElementById('a-voucher-create');
    if (!el || !createBtn) return;
    var vouchers = {};
    try { JSON.parse(el.textContent || '[]').forEach(function (v) { vouchers[v.id] = v; }); } catch (e) { /* noop */ }

    function resetForm() {
        document.getElementById('voucher-form').action = document.getElementById('voucher-form').getAttribute('data-store');
        document.getElementById('a-voucher-title').textContent = 'Tạo voucher';
        ['vf-campaign', 'vf-code', 'vf-value', 'vf-note'].forEach(function (id) { document.getElementById(id).value = ''; });
        document.getElementById('vf-limit').value = '1';
        document.getElementById('vf-peruser').value = 'once';
        document.getElementById('vf-type').value = 'percent';
        document.getElementById('vf-start').value = '';
        document.getElementById('vf-end').value = '';
        document.getElementById('vf-active').checked = true;
    }

    function fill(v, action) {
        document.getElementById('voucher-form').action = action || '';
        document.getElementById('a-voucher-title').textContent = 'Sửa voucher';
        document.getElementById('vf-campaign').value = v.campaign_name || '';
        document.getElementById('vf-code').value = v.code || '';
        document.getElementById('vf-limit').value = v.usage_limit || 1;
        document.getElementById('vf-peruser').value = v.per_user || 'once';
        document.getElementById('vf-type').value = v.discount_type || 'percent';
        document.getElementById('vf-value').value = v.discount_value || 0;
        document.getElementById('vf-start').value = (v.starts_at || '').slice(0, 10);
        document.getElementById('vf-end').value = (v.ends_at || '').slice(0, 10);
        document.getElementById('vf-note').value = v.note || '';
        document.getElementById('vf-active').checked = (v.is_active == 1);
    }

    createBtn.addEventListener('click', function () { resetForm(); document.getElementById('a-modal-voucher').hidden = false; });
    document.querySelectorAll('.js-voucher-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var v = vouchers[parseInt(btn.getAttribute('data-id'), 10)];
            if (!v) return;
            fill(v, btn.getAttribute('data-action'));
            document.getElementById('a-modal-voucher').hidden = false;
        });
    });
})();

/* Admin SEO: tooltip "?" bên cạnh tên trường */
(function () {
    'use strict';
    var helpEls = document.querySelectorAll('.seo-help');
    if (!helpEls.length) return;
    var box = document.createElement('div');
    box.className = 'seo-tip';
    box.hidden = true;
    document.body.appendChild(box);
    function close() { box.hidden = true; }
    helpEls.forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            var text = b.getAttribute('data-tip') || '';
            if (!text) return;
            box.textContent = text;
            box.hidden = false;
            var rect = b.getBoundingClientRect();
            var left = rect.left;
            if (left + box.offsetWidth > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - box.offsetWidth - 8);
            }
            box.style.top = (rect.bottom + 8) + 'px';
            box.style.left = left + 'px';
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.seo-help') && !e.target.closest('.seo-tip')) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

/* Admin emails: xem trước template */
(function () {
    'use strict';
    var el = document.getElementById('admin-emails-data');
    if (!el) return;
    var emails = {};
    try { emails = JSON.parse(el.textContent || '{}'); } catch (e) { return; }
    document.querySelectorAll('.js-email-preview').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = btn.getAttribute('data-type');
            var e = emails[t];
            if (!e) return;
            document.getElementById('a-email-title').textContent = 'Xem trước: ' + e.label;
            document.getElementById('a-email-frame').srcdoc = e.html;
            document.getElementById('a-modal-email').hidden = false;
        });
    });
})();


