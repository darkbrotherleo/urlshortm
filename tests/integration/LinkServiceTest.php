<?php
declare(strict_types=1);

use App\Repository\UrlRepository;
use App\Security\LinkType;
use App\Security\SlugGenerator;
use App\Security\SlugValidator;
use App\Security\UrlNormalizer;
use App\Service\LinkService;
use App\Service\LinkValidationException;

return function (TestSuite $suite): void {
    $make = function () {
        $pdo = make_sqlite();
        return [
            'svc' => new LinkService(
                new UrlRepository($pdo),
                new LinkType(new UrlNormalizer()),
                new SlugGenerator(),
                new SlugValidator()
            ),
            'pdo' => $pdo,
        ];
    };

    $baseData = [
        'link_type' => 'link', 'target' => 'example.com/abc', 'title' => 'Tiêu đề',
        'description' => 'Mô tả', 'thumbnail' => 'https://cdn.example.com/a.jpg',
        'pixels' => 'px1, px2', 'utm_campaign' => 'camp', 'utm_medium' => 'social',
        'utm_source' => 'fb', 'utm_term' => 'term', 'utm_content' => 'content',
        'custom_slug' => '', 'folder_id' => '', 'password' => '', 'password_clear' => '',
        'starts_at' => '', 'ends_at' => '', 'domain' => '',
    ];

    $suite->test('create lưu metadata + tự sinh slug + pixels JSON', function () use ($make, $baseData): void {
        ['svc' => $svc, 'pdo' => $pdo] = $make();
        $res = $svc->create($baseData, 1);

        $row = (new UrlRepository($pdo))->findById($res['id'], 1);
        assert_same('https://example.com/abc', $row['target_url']);
        assert_same('Tiêu đề', $row['title']);
        assert_same('Mô tả', $row['description']);
        assert_same('px1', json_decode((string) $row['pixels'], true)[0]);
        assert_same('camp', $row['utm_campaign']);
        assert_matches('/^[0-9a-zA-Z]{6}$/', $row['slug']);
        assert_same('link', $row['link_type']);
    });

    $suite->test('create custom slug + mật khẩu + thời gian', function () use ($make, $baseData): void {
        ['svc' => $svc, 'pdo' => $pdo] = $make();
        $data = array_merge($baseData, [
            'custom_slug' => 'quang-cao',
            'password' => 'matkhau1',
            'starts_at' => '2026-01-01T00:00',
            'ends_at' => '2030-01-01T00:00',
        ]);
        $res = $svc->create($data, 1);

        $row = (new UrlRepository($pdo))->findById($res['id'], 1);
        assert_same('quang-cao', $row['slug']);
        assert_true(password_verify('matkhau1', (string) $row['password_hash']));
        assert_same('2026-01-01 00:00:00', $row['starts_at']);
        assert_same('2030-01-01 00:00:00', $row['ends_at']);
    });

    $suite->test('custom slug trùng -> lỗi', function () use ($make, $baseData): void {
        ['svc' => $svc] = $make();
        $svc->create(array_merge($baseData, ['custom_slug' => 'trunglap']), 1);

        $thrown = false;
        try {
            $svc->create(array_merge($baseData, ['custom_slug' => 'trunglap']), 1);
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('custom slug sai định dạng / reserved -> lỗi', function () use ($make, $baseData): void {
        ['svc' => $svc] = $make();

        foreach (['ab', 'dai-qua-dai-16-ky-tu'] as $bad) {
            $thrown = false;
            try {
                $svc->create(array_merge($baseData, ['custom_slug' => $bad]), 1);
            } catch (LinkValidationException) {
                $thrown = true;
            }
            assert_true($thrown, 'slug nên bị từ chối: ' . $bad);
        }

        $thrown = false;
        try {
            $svc->create(array_merge($baseData, ['custom_slug' => 'dashboard']), 1);
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown, 'reserved word nên bị từ chối');
    });

    $suite->test('update giữ mật khẩu cũ khi bật toggle, đổi được slug', function () use ($make, $baseData): void {
        ['svc' => $svc, 'pdo' => $pdo] = $make();
        $res = $svc->create(array_merge($baseData, ['password' => 'matkhau1']), 1);
        $id = $res['id'];

        $svc->update($id, array_merge($baseData, ['custom_slug' => 'moi-slug', 'password_enabled' => '1']), 1);

        $row = (new UrlRepository($pdo))->findById($id, 1);
        assert_same('moi-slug', $row['slug']);
        assert_true(password_verify('matkhau1', (string) $row['password_hash']), 'mật khẩu cũ phải giữ nguyên');
    });

    $suite->test('update tắt toggle -> xoá mật khẩu', function () use ($make, $baseData): void {
        ['svc' => $svc, 'pdo' => $pdo] = $make();
        $res = $svc->create(array_merge($baseData, ['password' => 'matkhau1']), 1);

        $svc->update($res['id'], array_merge($baseData, ['password_enabled' => '0']), 1);

        $row = (new UrlRepository($pdo))->findById($res['id'], 1);
        assert_null($row['password_hash']);
    });

    $suite->test('update thời gian sai (end < start) -> lỗi', function () use ($make, $baseData): void {
        ['svc' => $svc] = $make();
        $res = $svc->create($baseData, 1);

        $thrown = false;
        try {
            $svc->update($res['id'], array_merge($baseData, [
                'starts_at' => '2030-01-01T00:00', 'ends_at' => '2029-01-01T00:00',
            ]), 1);
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('delete chỉ xoá link của user sở hữu', function () use ($make, $baseData): void {
        ['svc' => $svc, 'pdo' => $pdo] = $make();
        $res = $svc->create($baseData, 1);

        $svc->delete($res['id'], 2); // user khác -> không xoá
        assert_true((new UrlRepository($pdo))->findById($res['id'], 1) !== null);

        $svc->delete($res['id'], 1);
        assert_null((new UrlRepository($pdo))->findById($res['id'], 1));
    });
};
