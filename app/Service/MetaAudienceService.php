<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Lấy phân bổ nhân khẩu học (độ tuổi, giới tính) từ Meta Marketing API
 * (breakdown age,gender). Dữ liệu tổng hợp — không chứa PII.
 */
final class MetaAudienceService
{
    private const GRAPH_URL = 'https://graph.facebook.com/v21.0/';

    /** @var callable|null http fetcher để test */
    public function __construct(private readonly mixed $http = null)
    {
    }

    /**
     * @return array{age:array<int,array{label:string,count:int}>,gender:array<int,array{label:string,count:int}>}
     *
     * @throws \RuntimeException khi lỗi kết nối hoặc API trả error
     */
    public function fetch(string $adAccountId, string $accessToken): array
    {
        $url = self::GRAPH_URL
            . rawurlencode($adAccountId)
            . '/insights?fields=impressions&breakdown=age,gender&date_preset=last_90d&limit=500'
            . '&access_token=' . rawurlencode($accessToken);

        $body = $this->http !== null ? (string) call_user_func($this->http, $url) : $this->httpGet($url);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Phản hồi từ Meta không hợp lệ.');
        }
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? (string) ($data['error']['message'] ?? 'Lỗi từ Meta.') : 'Lỗi từ Meta.';
            throw new \RuntimeException($message);
        }

        $age = [];
        $gender = [];

        foreach (($data['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $impressions = (int) ($row['impressions'] ?? 0);
            $ageLabel = (string) ($row['age'] ?? 'Không rõ');
            $genderLabel = (string) ($row['gender'] ?? 'Không rõ');

            $age[$ageLabel] = ($age[$ageLabel] ?? 0) + $impressions;
            $gender[$genderLabel] = ($gender[$genderLabel] ?? 0) + $impressions;
        }

        arsort($age);
        arsort($gender);

        $toList = static function (array $map): array {
            $list = [];
            foreach ($map as $label => $count) {
                $list[] = ['label' => (string) $label, 'count' => $count];
            }

            return $list;
        };

        return [
            'age'    => $toList($age),
            'gender' => $toList($gender),
        ];
    }

    private function httpGet(string $url): string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException('Không thể kết nối tới Meta.');
        }

        return $body;
    }
}
