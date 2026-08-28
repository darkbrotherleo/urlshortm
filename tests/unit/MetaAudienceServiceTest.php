<?php
declare(strict_types=1);

use App\Service\MetaAudienceService;

return function (TestSuite $suite): void {
    $suite->test('fetch parse breakdown age/gender', function (): void {
        $fake = function (string $url): string {
            return json_encode(['data' => [
                ['impressions' => '100', 'age' => '18-24', 'gender' => 'female'],
                ['impressions' => '50', 'age' => '18-24', 'gender' => 'male'],
                ['impressions' => '200', 'age' => '25-34', 'gender' => 'female'],
            ]]);
        };

        $svc = new MetaAudienceService($fake);
        $r = $svc->fetch('act_123', 'token');

        assert_same('25-34', $r['age'][0]['label']);
        assert_same(200, $r['age'][0]['count']);
        assert_same('female', $r['gender'][0]['label']);
        assert_same(300, $r['gender'][0]['count']);
    });

    $suite->test('fetch trả lỗi API -> RuntimeException', function (): void {
        $fake = fn () => json_encode(['error' => ['message' => 'Invalid OAuth token']]);
        $svc = new MetaAudienceService($fake);

        $thrown = false;
        try {
            $svc->fetch('act_123', 'bad');
        } catch (RuntimeException $e) {
            $thrown = str_contains($e->getMessage(), 'Invalid OAuth');
        }
        assert_true($thrown);
    });

    $suite->test('fetch phản hồi rác -> RuntimeException', function (): void {
        $svc = new MetaAudienceService(fn () => 'khong-phai-json');
        $thrown = false;
        try {
            $svc->fetch('act_123', 'token');
        } catch (RuntimeException) {
            $thrown = true;
        }
        assert_true($thrown);
    });
};
