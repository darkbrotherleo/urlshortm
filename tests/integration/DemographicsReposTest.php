<?php
declare(strict_types=1);

use App\Repository\DemographicRepository;
use App\Repository\UserSettingsRepository;

return function (TestSuite $suite): void {
    $suite->test('user settings: set/get/upsert/delete', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')->execute(['d@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $repo = new UserSettingsRepository($pdo);
        $repo->set($uid, 'meta_ad_account', 'act_1');
        assert_same('act_1', $repo->get($uid, 'meta_ad_account'));

        $repo->set($uid, 'meta_ad_account', 'act_2'); // upsert
        assert_same('act_2', $repo->get($uid, 'meta_ad_account'));
        assert_null($repo->get($uid, 'khong-co'));

        $repo->delete($uid, 'meta_ad_account');
        assert_null($repo->get($uid, 'meta_ad_account'));
    });

    $suite->test('demographic snapshot: save/latest/deleteAll', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')->execute(['e@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $repo = new DemographicRepository($pdo);
        assert_null($repo->latest($uid));

        $repo->saveSnapshot($uid, ['age' => [['label' => '18-24', 'count' => 5]]]);
        $latest = $repo->latest($uid);
        assert_same('18-24', $latest['payload']['age'][0]['label']);
        assert_true($latest['fetched_at'] !== '');

        $repo->saveSnapshot($uid, ['age' => [['label' => '25-34', 'count' => 9]]]);
        assert_same('25-34', $repo->latest($uid)['payload']['age'][0]['label']);

        $repo->deleteAll($uid);
        assert_null($repo->latest($uid));
    });
};
