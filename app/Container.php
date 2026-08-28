<?php
declare(strict_types=1);

namespace App;

use App\Controller\HomeController;
use App\Controller\RedirectController;
use App\Controller\StatsController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\LinkController;
use App\Controller\SettingsController;
use App\Controller\AdminController;
use App\Controller\AdminDashboardController;
use App\Controller\AdminUsersController;
use App\Controller\AdminPackagesController;
use App\Controller\AdminPaymentsController;
use App\Controller\AdminOrdersController;
use App\Controller\AdminLinksController;
use App\Controller\AdminVouchersController;
use App\Controller\AdminDomainsController;
use App\Controller\AdminSettingsController;
use App\Controller\AdminEmailsController;
use App\Controller\CheckoutController;
use App\Repository\AdminRepository;
use App\Repository\RateLimitRepository;
use App\Repository\UrlRepository;
use App\Repository\UserRepository;
use App\Repository\FolderRepository;
use App\Repository\PixelRepository;
use App\Repository\DomainRepository;
use App\Repository\UtmProfileRepository;
use App\Repository\ClickEventRepository;
use App\Repository\UserSettingsRepository;
use App\Repository\DemographicRepository;
use App\Repository\PackageRepository;
use App\Repository\SettingRepository;
use App\Repository\OrderRepository;
use App\Repository\VoucherRepository;
use App\Repository\MediaRepository;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Security\SlugGenerator;
use App\Security\SlugValidator;
use App\Security\UrlNormalizer;
use App\Security\LinkType;
use App\Service\ShortUrlService;
use App\Service\AuthService;
use App\Service\LinkService;
use App\Service\DomainService;
use App\Service\MetaAudienceService;
use App\Service\AdminAuthService;
use App\Service\UserPlanService;
use App\Service\VoucherService;
use App\Service\SiteSettingsService;
use App\Service\ImageProcessor;
use App\Service\Mailer;
use App\Service\EmailTemplates;

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
            new RateLimiter($this->rateLimitRepository()),
            $this->userPlanService()
        );
    }

    public function homeController(): HomeController
    {
        return $this->services[HomeController::class] ??= new HomeController(
            $this->shortUrlService(),
            $this->packageRepository(),
            new Csrf()
        );
    }

    public function redirectController(): RedirectController
    {
        return $this->services[RedirectController::class] ??= new RedirectController(
            $this->urlRepository(),
            $this->clickEventRepository(),
            new \App\Tracking\UserAgentParser(),
            new \App\Tracking\CountryLookup(),
            $this->userPlanService(),
            new Csrf()
        );
    }

    public function clickEventRepository(): ClickEventRepository
    {
        return $this->services[ClickEventRepository::class] ??= new ClickEventRepository(Database::default());
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
            $this->pixelRepository(),
            $this->domainRepository(),
            $this->utmProfileRepository(),
            $this->clickEventRepository(),
            $this->userSettingsRepository(),
            $this->demographicRepository(),
            $this->authService(),
            $this->userPlanService(),
            new Csrf()
        );
    }

    public function settingsController(): SettingsController
    {
        return $this->services[SettingsController::class] ??= new SettingsController(
            $this->pixelRepository(),
            $this->utmProfileRepository(),
            $this->userSettingsRepository(),
            $this->demographicRepository(),
            $this->domainService(),
            new MetaAudienceService(),
            $this->userPlanService(),
            new Csrf()
        );
    }

    public function userSettingsRepository(): UserSettingsRepository
    {
        return $this->services[UserSettingsRepository::class] ??= new UserSettingsRepository(Database::default());
    }

    public function demographicRepository(): DemographicRepository
    {
        return $this->services[DemographicRepository::class] ??= new DemographicRepository(Database::default());
    }

    public function adminRepository(): AdminRepository
    {
        return $this->services[AdminRepository::class] ??= new AdminRepository(Database::default());
    }

    public function adminAuthService(): AdminAuthService
    {
        return $this->services[AdminAuthService::class] ??= new AdminAuthService(
            $this->adminRepository(),
            new RateLimiter($this->rateLimitRepository())
        );
    }

    public function adminController(): AdminController
    {
        return $this->services[AdminController::class] ??= new AdminController($this->adminAuthService(), new Csrf());
    }

    public function adminDashboardController(): AdminDashboardController
    {
        return $this->services[AdminDashboardController::class] ??= new AdminDashboardController();
    }

    public function adminUsersController(): AdminUsersController
    {
        return $this->services[AdminUsersController::class] ??= new AdminUsersController(
            $this->userRepository(),
            new Csrf()
        );
    }

    public function adminPackagesController(): AdminPackagesController
    {
        return $this->services[AdminPackagesController::class] ??= new AdminPackagesController(
            $this->packageRepository(),
            new Csrf()
        );
    }

    public function packageRepository(): PackageRepository
    {
        return $this->services[PackageRepository::class] ??= new PackageRepository(Database::default());
    }

    public function settingRepository(): SettingRepository
    {
        return $this->services[SettingRepository::class] ??= new SettingRepository(Database::default());
    }

    public function orderRepository(): OrderRepository
    {
        return $this->services[OrderRepository::class] ??= new OrderRepository(Database::default());
    }

    public function checkoutController(): CheckoutController
    {
        return $this->services[CheckoutController::class] ??= new CheckoutController(
            $this->packageRepository(),
            $this->orderRepository(),
            $this->settingRepository(),
            $this->userRepository(),
            $this->voucherService(),
            new Csrf()
        );
    }

    public function adminPaymentsController(): AdminPaymentsController
    {
        return $this->services[AdminPaymentsController::class] ??= new AdminPaymentsController(
            $this->settingRepository(),
            new Csrf()
        );
    }

    public function adminOrdersController(): AdminOrdersController
    {
        return $this->services[AdminOrdersController::class] ??= new AdminOrdersController(
            $this->orderRepository(),
            $this->packageRepository(),
            $this->userRepository(),
            new Csrf()
        );
    }

    public function adminLinksController(): AdminLinksController
    {
        return $this->services[AdminLinksController::class] ??= new AdminLinksController(
            $this->urlRepository(),
            new Csrf()
        );
    }

    public function voucherRepository(): VoucherRepository
    {
        return $this->services[VoucherRepository::class] ??= new VoucherRepository(Database::default());
    }

    public function voucherService(): VoucherService
    {
        return $this->services[VoucherService::class] ??= new VoucherService($this->voucherRepository());
    }

    public function adminVouchersController(): AdminVouchersController
    {
        return $this->services[AdminVouchersController::class] ??= new AdminVouchersController(
            $this->voucherRepository(),
            new Csrf()
        );
    }

    public function adminDomainsController(): AdminDomainsController
    {
        return $this->services[AdminDomainsController::class] ??= new AdminDomainsController(
            $this->domainRepository(),
            $this->userPlanService(),
            new Csrf()
        );
    }

    public function adminSettingsController(): AdminSettingsController
    {
        return $this->services[AdminSettingsController::class] ??= new AdminSettingsController(
            $this->settingRepository(),
            $this->siteSettingsService(),
            $this->mediaRepository(),
            new ImageProcessor(),
            new Csrf()
        );
    }

    public function adminEmailsController(): AdminEmailsController
    {
        return $this->services[AdminEmailsController::class] ??= new AdminEmailsController(
            $this->emailTemplates(),
            $this->mailer(),
            new Csrf()
        );
    }

    public function siteSettingsService(): SiteSettingsService
    {
        return $this->services[SiteSettingsService::class] ??= new SiteSettingsService($this->settingRepository());
    }

    public function mailer(): Mailer
    {
        return $this->services[Mailer::class] ??= new Mailer($this->settingRepository());
    }

    public function emailTemplates(): EmailTemplates
    {
        return $this->services[EmailTemplates::class] ??= new EmailTemplates($this->siteSettingsService());
    }

    public function mediaRepository(): MediaRepository
    {
        return $this->services[MediaRepository::class] ??= new MediaRepository(Database::default());
    }

    public function userPlanService(): UserPlanService
    {
        return $this->services[UserPlanService::class] ??= new UserPlanService(Database::default());
    }

    public function domainService(): DomainService
    {
        return $this->services[DomainService::class] ??= new DomainService($this->domainRepository());
    }

    public function domainRepository(): DomainRepository
    {
        return $this->services[DomainRepository::class] ??= new DomainRepository(Database::default());
    }

    public function utmProfileRepository(): UtmProfileRepository
    {
        return $this->services[UtmProfileRepository::class] ??= new UtmProfileRepository(Database::default());
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
            new SlugValidator(),
            $this->userPlanService()
        );
    }

    public function linkController(): LinkController
    {
        return $this->services[LinkController::class] ??= new LinkController(
            $this->urlRepository(),
            $this->folderRepository(),
            $this->pixelRepository(),
            $this->domainRepository(),
            $this->utmProfileRepository(),
            $this->linkService(),
            new LinkType(new UrlNormalizer()),
            $this->siteSettingsService(),
            new ImageProcessor(),
            new Csrf()
        );
    }

    public function pixelRepository(): PixelRepository
    {
        return $this->services[PixelRepository::class] ??= new PixelRepository(Database::default());
    }
}
