<?php
declare(strict_types=1);

namespace App;

use App\Controller\HomeController;
use App\Controller\RedirectController;
use App\Controller\StatsController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\LinkController;
use App\Repository\RateLimitRepository;
use App\Repository\UrlRepository;
use App\Repository\UserRepository;
use App\Repository\FolderRepository;
use App\Repository\PixelRepository;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Security\SlugGenerator;
use App\Security\SlugValidator;
use App\Security\UrlNormalizer;
use App\Security\LinkType;
use App\Service\ShortUrlService;
use App\Service\AuthService;
use App\Service\LinkService;

final class Container
{
    private static ?Container $instance = null;
    private array $services = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
        Database::reset();
    }

    public function urlRepository(): UrlRepository
    {
        return $this->services[UrlRepository::class] ??= new UrlRepository(Database::default());
    }

    public function rateLimitRepository(): RateLimitRepository
    {
        return $this->services[RateLimitRepository::class] ??= new RateLimitRepository(Database::default());
    }

    public function shortUrlService(): ShortUrlService
    {
        return $this->services[ShortUrlService::class] ??= new ShortUrlService(
            $this->urlRepository(),
            new UrlNormalizer(),
            new SlugGenerator(),
            new RateLimiter($this->rateLimitRepository())
        );
    }

    public function homeController(): HomeController
    {
        return $this->services[HomeController::class] ??= new HomeController(
            $this->shortUrlService(),
            new Csrf()
        );
    }

    public function redirectController(): RedirectController
    {
        return $this->services[RedirectController::class] ??= new RedirectController(
            $this->urlRepository(),
            new Csrf()
        );
    }

    public function statsController(): StatsController
    {
        return $this->services[StatsController::class] ??= new StatsController(
            $this->urlRepository(),
            new SlugValidator()
        );
    }

    public function userRepository(): UserRepository
    {
        return $this->services[UserRepository::class] ??= new UserRepository(Database::default());
    }

    public function authService(): AuthService
    {
        return $this->services[AuthService::class] ??= new AuthService(
            $this->userRepository(),
            new RateLimiter($this->rateLimitRepository())
        );
    }

    public function authController(): AuthController
    {
        return $this->services[AuthController::class] ??= new AuthController(
            $this->authService(),
            new Csrf()
        );
    }

    public function dashboardController(): DashboardController
    {
        return $this->services[DashboardController::class] ??= new DashboardController(
            $this->urlRepository(),
            $this->folderRepository(),
            $this->userRepository(),
            new Csrf()
        );
    }

    public function folderRepository(): FolderRepository
    {
        return $this->services[FolderRepository::class] ??= new FolderRepository(Database::default());
    }

    public function linkService(): LinkService
    {
        return $this->services[LinkService::class] ??= new LinkService(
            $this->urlRepository(),
            new LinkType(new UrlNormalizer()),
            new SlugGenerator(),
            new SlugValidator()
        );
    }

    public function linkController(): LinkController
    {
        return $this->services[LinkController::class] ??= new LinkController(
            $this->urlRepository(),
            $this->folderRepository(),
            $this->pixelRepository(),
            $this->linkService(),
            new LinkType(new UrlNormalizer()),
            new Csrf()
        );
    }

    public function pixelRepository(): PixelRepository
    {
        return $this->services[PixelRepository::class] ??= new PixelRepository(Database::default());
    }
}
