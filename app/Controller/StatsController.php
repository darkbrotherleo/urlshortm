<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UrlRepository;
use App\Security\SlugValidator;

final class StatsController
{
    public function __construct(
        private readonly UrlRepository $repository,
        private readonly SlugValidator $slugValidator
    ) {
    }

    public function show(string $slug): never
    {
        if (!$this->slugValidator->isValid($slug)) {
            $this->json(['error' => 'Slug không hợp lệ.'], 400);
        }

        $row = $this->repository->findBySlug($slug);

        if ($row === null) {
            $this->json(['error' => 'Không tìm thấy link.'], 404);
        }

        $this->json([
            'slug'        => $row['slug'],
            'click_count' => (int) $row['click_count'],
            'created_at'  => $row['created_at'],
        ], 200);
    }

    private function json(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
