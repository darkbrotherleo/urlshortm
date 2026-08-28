<?php
declare(strict_types=1);

use App\Repository\UrlRepository;
use App\Security\LinkType;
use App\Security\SlugGenerator;
use App\Security\SlugValidator;
use App\Security\UrlNormalizer;
use App\Service\LinkService;
use App\Service\LinkValidationException;
use App\Service\UserPlanService;

return function (TestSuite $suite): void {
    $suite->test('planOf: không có subscription -> gói Free mặc định', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_links, max_clicks, max_custom_domains, max_pixels, has_analytics) VALUES (?,?,?,?,?,?,?)')
            ->execute(['free', 'Miễn phí', 20, 10000, 5, 5, 1]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $plan = (new UserPlanService($pdo))->planOf($uid);
        assert_same('free', $plan['code']);
        assert_same('Miễn phí', $plan['name']);
    });

    $suite->test('planOf: subscription active -> lấy gói đó, bỏ qua sub expired', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_links) VALUES (?,?,?)')->execute(['free', 'Miễn phí', 20]);
        $pdo->prepare('INSERT INTO plans (code, name, max_links) VALUES (?,?,?)')->execute(['pro', 'Pro', -1]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, status) VALUES (?,?,'expired'), (?,?,'active')")
            ->execute([$uid, 1, $uid, 2]);

        $plan = (new UserPlanService($pdo))->planOf($uid);
        assert_same('pro', $plan['code']);
    });

    $suite->test('canCreateLink theo giới hạn gói', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_links) VALUES (?,?,?)')->execute(['free', 'Miễn phí', 2]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $svc = new UserPlanService($pdo);

        assert_true($svc->canCreateLink($uid));
        $ins = $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?,?,?)');
        $ins->execute(['aaaaaa', 'https://a.com', $uid]);
        assert_true($svc->canCreateLink($uid));
        $ins->execute(['bbbbbb', 'https://b.com', $uid]);
        assert_false($svc->canCreateLink($uid), 'đã đạt 2/2 link phải chặn');

        // Gói Pro (-1) -> không giới hạn
        $pdo->prepare('INSERT INTO plans (code, name, max_links) VALUES (?,?,?)')->execute(['pro', 'Pro', -1]);
        $proId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, status) VALUES (?,?,'active')")->execute([$uid, $proId]);
        assert_true($svc->canCreateLink($uid));
    });

    $suite->test('canClick: đếm click tháng này theo giới hạn', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_clicks) VALUES (?,?,?)')->execute(['free', 'Miễn phí', 2]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $svc = new UserPlanService($pdo);

        assert_true($svc->canClick($uid));
        $ins = $pdo->prepare("INSERT INTO click_events (link_id, user_id, opened_at, ip_hash) VALUES (1,?,?, 'h')");
        $ins->execute([$uid, date('Y-m-d H:i:s')]);
        $ins->execute([$uid, date('Y-m-d H:i:s')]);
        assert_false($svc->canClick($uid), 'đạt giới hạn click tháng phải chặn');
    });

    $suite->test('canAddDomain/canAddPixel theo giới hạn', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_custom_domains, max_pixels) VALUES (?,?,?,?)')->execute(['free', 'Miễn phí', 1, 2]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $svc = new UserPlanService($pdo);

        assert_true($svc->canAddDomain($uid));
        $pdo->prepare('INSERT INTO domains (user_id, domain) VALUES (?,?)')->execute([$uid, 'x.test']);
        assert_false($svc->canAddDomain($uid));

        assert_true($svc->canAddPixel($uid));
        $pdo->prepare('INSERT INTO pixels (user_id, code) VALUES (?,?)')->execute([$uid, 'p1']);
        assert_true($svc->canAddPixel($uid));
        $pdo->prepare('INSERT INTO pixels (user_id, code) VALUES (?,?)')->execute([$uid, 'p2']);
        assert_false($svc->canAddPixel($uid));
    });

    $suite->test('featureEnabled đọc cờ của gói', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, has_api_access, has_qr_code) VALUES (?,?,?,?)')->execute(['free', 'Miễn phí', 0, 1]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $svc = new UserPlanService($pdo);

        assert_true($svc->featureEnabled($uid, 'qr_code'));
        assert_false($svc->featureEnabled($uid, 'api_access'));
    });

    $suite->test('LinkService chặn tạo link khi đạt giới hạn gói', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, max_links) VALUES (?,?,?)')->execute(['free', 'Miễn phí', 1]);
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $plan = new UserPlanService($pdo);

        $svc = new LinkService(
            new UrlRepository($pdo),
            new LinkType(new UrlNormalizer()),
            new SlugGenerator(),
            new SlugValidator(),
            $plan
        );
        $svc->create(['target' => 'https://a.com', 'link_type' => 'link'], (int) $uid);

        $thrown = null;
        try {
            $svc->create(['target' => 'https://b.com', 'link_type' => 'link'], (int) $uid);
        } catch (LinkValidationException $e) {
            $thrown = $e;
        }
        assert_true($thrown !== null, 'vượt giới hạn link phải bị chặn');
        assert_contains('giới hạn', $thrown->getMessage());
    });
};
