<?php
declare(strict_types=1);

use App\Repository\ClickEventRepository;

return function (TestSuite $suite): void {
    $suite->test('report methods tổng hợp đúng theo user', function (): void {
        $pdo = make_sqlite();

        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')->execute(['u@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?, ?, ?)')->execute(['aaaaaa', 'https://a.com', $uid]);
        $l1 = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?, ?, ?)')->execute(['bbbbbb', 'https://b.com', $uid]);
        $l2 = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO click_events (link_id, user_id, opened_at, ip_hash, ip_address, user_agent, referrer, country, device, browser, os) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$l1, $uid, '2026-08-27 10:00:00', 'h1', '1.2.3.4', 'ua', 'https://fb.com', 'VN', 'mobile', 'Chrome', 'Android']);
        $ins->execute([$l1, $uid, '2026-08-27 11:00:00', 'h1', '1.2.3.4', 'ua', null, 'VN', 'mobile', 'Chrome', 'Android']);
        $ins->execute([$l1, $uid, '2026-08-28 09:00:00', 'h2', '5.6.7.8', 'ua', 'https://google.com', 'US', 'desktop', 'Safari', 'iOS']);
        $ins->execute([$l2, $uid, '2026-08-28 10:00:00', 'h3', '9.9.9.9', 'ua', null, 'US', 'desktop', 'Safari', 'macOS']);

        $repo = new ClickEventRepository($pdo);

        $sum = $repo->reportSummary($uid);
        assert_same(4, $sum['total_clicks']);
        assert_same(2, $sum['total_days']);
        assert_same(2, $sum['total_links']);
        assert_same(2.0, $sum['avg_per_day']);

        $byDay = $repo->reportByDay($uid);
        assert_same(2, count($byDay));
        assert_same(2, $byDay[0]['count']);
        assert_same(2, $byDay[1]['count']);

        $dev = $repo->reportByFactor('device', $uid);
        assert_same(2, count($dev));
        assert_same(2, $dev[0]['count']);

        $br = $repo->reportByFactor('browser', $uid);
        assert_same(2, $br[0]['count']);

        $ref = $repo->reportByFactor('referrer', $uid);
        assert_same('(trực tiếp)', $ref[0]['label']);
        assert_same(2, $ref[0]['count']);

        $country = $repo->reportByFactor('country', $uid);
        assert_same(2, $country[0]['count']);

        $top = $repo->reportTopLinks($uid);
        assert_same(2, count($top));
        assert_same('aaaaaa', $top[0]['slug']);
        assert_same(3, $top[0]['count']);

        $sum2 = $repo->reportSummary($uid, $l2);
        assert_same(1, $sum2['total_clicks']);

        // Chi tiết lượt mở + phân trang
        $events = $repo->reportEvents($uid, null, null, null, 50, 0);
        assert_same(4, count($events));
        assert_same('2026-08-28 10:00:00', $events[0]['opened_at']);
        assert_same('bbbbbb', $events[0]['slug']);
        assert_same('9.9.9.9', $events[0]['ip_address'], 'reportEvents phải trả IP thật');

        assert_same(4, $repo->countReportEvents($uid));

        $page1 = $repo->reportEvents($uid, null, null, null, 2, 0);
        $page2 = $repo->reportEvents($uid, null, null, null, 2, 2);
        assert_same(2, count($page1));
        assert_same(2, count($page2));
        assert_same('2026-08-27 10:00:00', $page2[1]['opened_at']);
    });
};
