/* QR Designer — tự render QR theo shape/corner style + màu, preview live,
   tải SVG/PNG. Dựa trên qrcode-generator (global `qrcode`). */
(function () {
    'use strict';

    var modal = document.getElementById('qr-modal');
    if (!modal) return;

    var canvas = document.getElementById('qr-canvas');
    var shapeStyle = document.getElementById('qr-shape-style');
    var cornerStyle = document.getElementById('qr-corner-style');
    var shapeColor = document.getElementById('qr-shape-color');
    var cornerColor = document.getElementById('qr-corner-color');

    var CELL = 6;       // px/module
    var QUIET = 4;      // số module quiet zone
    var SCALE = 4;      // độ nét canvas (PNG)
    var currentUrl = '';

    function finderZones(count) {
        var last = count - 7;
        return [
            { r0: 0, c0: 0, r1: 6, c1: 6 },
            { r0: 0, c0: last, r1: 6, c1: count - 1 },
            { r0: last, c0: 0, r1: count - 1, c1: 6 }
        ];
    }

    function inZone(r, c, zones) {
        for (var i = 0; i < zones.length; i++) {
            var z = zones[i];
            if (r >= z.r0 && r <= z.r1 && c >= z.c0 && c <= z.c1) return true;
        }
        return false;
    }

    function buildMatrix() {
        var qr = qrcode(0, 'M');
        qr.addData(currentUrl);
        qr.make();
        var count = qr.getModuleCount();
        var zones = finderZones(count);
        var dark = [];
        var finder = [];
        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (!qr.isDark(r, c)) continue;
                if (inZone(r, c, zones)) {
                    finder.push({ r: r, c: c });
                } else {
                    dark.push({ r: r, c: c });
                }
            }
        }
        return { count: count, dark: dark, finder: finder };
    }

    function roundRect(ctx, x, y, w, h, r) {
        if (r > w / 2) r = w / 2;
        if (r > h / 2) r = h / 2;
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
        ctx.fill();
    }

    function drawData(ctx, m, cell) {
        var style = shapeStyle.value;
        for (var i = 0; i < m.dark.length; i++) {
            var x = (m.dark[i].c + QUIET) * cell;
            var y = (m.dark[i].r + QUIET) * cell;
            if (style === 'dots') {
                ctx.beginPath();
                ctx.arc(x + cell / 2, y + cell / 2, cell * 0.36, 0, Math.PI * 2);
                ctx.fill();
            } else {
                var inset = style === 'square' ? 0 : cell * 0.08;
                var s = cell - inset * 2;
                var radius = style === 'extra-rounded' ? cell * 0.5 : cell * 0.28;
                roundRect(ctx, x + inset, y + inset, s, s, radius);
            }
        }
    }

    function drawCorners(ctx, m, cell) {
        var style = cornerStyle.value;
        for (var i = 0; i < m.finder.length; i++) {
            var x = (m.finder[i].c + QUIET) * cell;
            var y = (m.finder[i].r + QUIET) * cell;
            var inset = style === 'square' ? 0 : cell * 0.06;
            var s = cell - inset * 2;
            var radius = style === 'extra-rounded' ? cell * 0.5 : cell * 0.22;
            roundRect(ctx, x + inset, y + inset, s, s, radius);
        }
    }

    function renderCanvas(m) {
        var size = (m.count + QUIET * 2) * CELL;
        canvas.width = size * SCALE;
        canvas.height = size * SCALE;
        var ctx = canvas.getContext('2d');
        ctx.setTransform(SCALE, 0, 0, SCALE, 0, 0);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.fillStyle = shapeColor.value;
        drawData(ctx, m, CELL);
        ctx.fillStyle = cornerColor.value;
        drawCorners(ctx, m, CELL);
        canvas.dataset.size = String(size);
    }

    function shapeTag(m, cell, quiet) {
        var style = shapeStyle.value;
        var color = shapeColor.value;
        var out = '';
        for (var i = 0; i < m.dark.length; i++) {
            var x = (m.dark[i].c + quiet) * cell;
            var y = (m.dark[i].r + quiet) * cell;
            if (style === 'dots') {
                out += '<circle cx="' + (x + cell / 2) + '" cy="' + (y + cell / 2) + '" r="' + (cell * 0.36) + '" fill="' + color + '"/>';
            } else {
                var inset = style === 'square' ? 0 : cell * 0.08;
                var s = cell - inset * 2;
                var radius = style === 'extra-rounded' ? cell * 0.5 : cell * 0.28;
                out += '<rect x="' + (x + inset) + '" y="' + (y + inset) + '" width="' + s + '" height="' + s + '" rx="' + radius + '" fill="' + color + '"/>';
            }
        }
        return out;
    }

    function cornerTag(m, cell, quiet) {
        var style = cornerStyle.value;
        var color = cornerColor.value;
        var out = '';
        for (var i = 0; i < m.finder.length; i++) {
            var x = (m.finder[i].c + quiet) * cell;
            var y = (m.finder[i].r + quiet) * cell;
            var inset = style === 'square' ? 0 : cell * 0.06;
            var s = cell - inset * 2;
            var radius = style === 'extra-rounded' ? cell * 0.5 : cell * 0.22;
            out += '<rect x="' + (x + inset) + '" y="' + (y + inset) + '" width="' + s + '" height="' + s + '" rx="' + radius + '" fill="' + color + '"/>';
        }
        return out;
    }

    function buildSvg() {
        var m = buildMatrix();
        var cell = 10;
        var size = (m.count + QUIET * 2) * cell;
        return '<?xml version="1.0" encoding="UTF-8"?>'
            + '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '">'
            + '<rect width="' + size + '" height="' + size + '" fill="#ffffff"/>'
            + shapeTag(m, cell, QUIET)
            + cornerTag(m, cell, QUIET)
            + '</svg>';
    }

    function render() {
        if (!currentUrl) return;
        renderCanvas(buildMatrix());
    }

    function download(filename, dataUrl) {
        var a = document.createElement('a');
        a.href = dataUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function fileName(ext) {
        var slug = currentUrl.split('/').filter(Boolean).pop() || 'qr-code';
        return 'qr-' + slug + '.' + ext;
    }

    /* ---- sự kiện ---- */
    document.querySelectorAll('.js-qr').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentUrl = btn.getAttribute('data-url') || '';
            render();
            modal.hidden = false;
            var close = document.getElementById('qr-close');
            if (close) close.focus();
        });
    });

    [shapeStyle, cornerStyle, shapeColor, cornerColor].forEach(function (el) {
        el.addEventListener('change', render);
        el.addEventListener('input', render);
    });

    var closeBtn = document.getElementById('qr-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () { modal.hidden = true; });
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.hidden = true;
    });

    var saveSvg = document.getElementById('qr-save-svg');
    if (saveSvg) {
        saveSvg.addEventListener('click', function () {
            var blob = new Blob([buildSvg()], { type: 'image/svg+xml;charset=utf-8' });
            download(fileName('svg'), URL.createObjectURL(blob));
        });
    }

    var savePng = document.getElementById('qr-save-png');
    if (savePng) {
        savePng.addEventListener('click', function () {
            var dataUrl = canvas.toDataURL('image/png');
            download(fileName('png'), dataUrl);
        });
    }
})();
