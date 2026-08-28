<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$match = (new App\Router())->match($method, $path);
$container = App\Container::getInstance();

switch ($match['handler']) {
    case 'home':
        $container->homeController()->index();
        break;

    case 'pricing':
        $container->homeController()->pricing();
        break;

    case 'sitemap':
        $container->homeController()->sitemap();
        break;

    case 'robots_txt':
        $container->homeController()->robotsTxt();
        break;

    case 'features':
        $container->homeController()->features();
        break;

    case 'shorten':
        $container->homeController()->shorten();
        break;

    case 'redirect':
        $container->redirectController()->redirect((string) $match['params']['slug']);
        break;

    case 'stats':
        $container->statsController()->show((string) $match['params']['slug']);
        break;

    case 'register':
        if ($method === 'POST') {
            $container->authController()->register();
        }
        $container->authController()->showRegister();
        break;

    case 'login':
        if ($method === 'POST') {
            $container->authController()->login();
        }
        $container->authController()->showLogin();
        break;

    case 'logout':
        $container->authController()->logout();
        break;

    case 'activate':
        $container->authController()->activate();
        break;

    case 'forgot_password':
        if ($method === 'POST') {
            $container->authController()->requestReset();
        }
        $container->authController()->showForgot();
        break;

    case 'reset_password':
        if ($method === 'POST') {
            $container->authController()->doReset();
        }
        $container->authController()->showReset();
        break;

    case 'admin_login':
        if ($method === 'POST') {
            $container->adminController()->login();
        }
        $container->adminController()->showLogin();
        break;

    case 'admin_logout':
        $container->adminController()->logout();
        break;

    case 'admin_dashboard':
        $container->adminDashboardController()->index();
        break;

    case 'admin_users':
        $container->adminUsersController()->index();
        break;

    case 'admin_users_update':
        $container->adminUsersController()->update();
        break;

    case 'admin_packages':
        $container->adminPackagesController()->index();
        break;

    case 'admin_packages_new':
        $container->adminPackagesController()->createForm();
        break;

    case 'admin_packages_edit':
        $container->adminPackagesController()->editForm((int) $match['params']['id']);
        break;

    case 'admin_packages_store':
        $container->adminPackagesController()->store();
        break;

    case 'admin_packages_update':
        $container->adminPackagesController()->update((int) $match['params']['id']);
        break;

    case 'admin_packages_delete':
        $container->adminPackagesController()->delete((int) $match['params']['id']);
        break;

    case 'admin_packages_toggle':
        $container->adminPackagesController()->toggle((int) $match['params']['id']);
        break;

    case 'checkout':
        $container->checkoutController()->index();
        break;

    case 'checkout_pay':
        $container->checkoutController()->pay();
        break;

    case 'checkout_success':
        $container->checkoutController()->success();
        break;

    case 'checkout_cancel':
        $container->checkoutController()->cancel();
        break;

    case 'checkout_invoice':
        $container->checkoutController()->invoice((string) $match['params']['code']);
        break;

    case 'admin_payments':
        $container->adminPaymentsController()->index();
        break;

    case 'admin_payments_save':
        $container->adminPaymentsController()->save();
        break;

    case 'admin_orders':
        $container->adminOrdersController()->index();
        break;

    case 'admin_orders_status':
        $container->adminOrdersController()->updateStatus((int) $match['params']['id']);
        break;

    case 'admin_links':
        $container->adminLinksController()->index();
        break;

    case 'admin_links_toggle':
        $container->adminLinksController()->toggle((int) $match['params']['id']);
        break;

    case 'admin_links_update':
        $container->adminLinksController()->update((int) $match['params']['id']);
        break;

    case 'admin_vouchers':
        $container->adminVouchersController()->index();
        break;

    case 'admin_vouchers_store':
        $container->adminVouchersController()->store();
        break;

    case 'admin_vouchers_update':
        $container->adminVouchersController()->update((int) $match['params']['id']);
        break;

    case 'admin_vouchers_toggle':
        $container->adminVouchersController()->toggle((int) $match['params']['id']);
        break;

    case 'admin_domains':
        $container->adminDomainsController()->index();
        break;

    case 'admin_domains_system_add':
        $container->adminDomainsController()->addSystem();
        break;

    case 'admin_domains_system_default':
        $container->adminDomainsController()->setDefault((int) $match['params']['id']);
        break;

    case 'admin_domains_system_toggle':
        $container->adminDomainsController()->toggleSystem((int) $match['params']['id']);
        break;

    case 'admin_domains_system_delete':
        $container->adminDomainsController()->deleteSystem((int) $match['params']['id']);
        break;

    case 'admin_domains_user_toggle':
        $container->adminDomainsController()->toggleUser((int) $match['params']['id']);
        break;

    case 'admin_domains_user_delete':
        $container->adminDomainsController()->deleteUser((int) $match['params']['id']);
        break;

    case 'admin_settings':
        $container->adminSettingsController()->systemInfo();
        break;

    case 'admin_settings_website':
        $container->adminSettingsController()->website();
        break;

    case 'admin_settings_website_save':
        $container->adminSettingsController()->saveWebsite();
        break;

    case 'admin_settings_invoice':
        $container->adminSettingsController()->invoice();
        break;

    case 'admin_settings_invoice_save':
        $container->adminSettingsController()->saveInvoice();
        break;

    case 'admin_settings_smtp':
        $container->adminSettingsController()->smtp();
        break;

    case 'admin_settings_smtp_save':
        $container->adminSettingsController()->saveSmtp();
        break;

    case 'admin_settings_smtp_test':
        $container->adminSettingsController()->smtpTest();
        break;

    case 'admin_settings_media':
        $container->adminSettingsController()->media();
        break;

    case 'admin_settings_media_save':
        $container->adminSettingsController()->saveMedia();
        break;

    case 'admin_settings_media_upload':
        $container->adminSettingsController()->mediaUpload();
        break;

    case 'admin_settings_media_delete':
        $container->adminSettingsController()->mediaDelete((int) $match['params']['id']);
        break;

    case 'admin_settings_seo':
        $container->adminSettingsController()->seo();
        break;

    case 'admin_settings_seo_save':
        $container->adminSettingsController()->saveSeo();
        break;

    case 'admin_emails':
        $container->adminEmailsController()->index();
        break;

    case 'admin_emails_send':
        $container->adminEmailsController()->sendTest();
        break;

    case 'dashboard':
        $container->dashboardController()->index();
        break;

    case 'folder_create':
        $container->dashboardController()->createFolder();
        break;

    case 'folder_delete':
        $container->dashboardController()->deleteFolder();
        break;

    case 'link_folder':
        $container->dashboardController()->assignLink();
        break;

    case 'settings_update':
        $container->dashboardController()->updateSettings();
        break;

    case 'unlock':
        $container->redirectController()->unlock((string) $match['params']['slug']);
        break;

    case 'link_new':
        $container->linkController()->createForm();
        break;

    case 'link_store':
        $container->linkController()->store();
        break;

    case 'link_edit':
        $container->linkController()->editForm((int) $match['params']['id']);
        break;

    case 'link_update':
        $container->linkController()->update((int) $match['params']['id']);
        break;

    case 'link_delete':
        $container->linkController()->destroy((int) $match['params']['id']);
        break;

    case 'link_bulk':
        $container->linkController()->bulk();
        break;

    case 'report_export':
        $container->dashboardController()->exportReport();
        break;

    case 'pixel_create':
        $container->settingsController()->createPixel();
        break;

    case 'pixel_delete':
        $container->settingsController()->deletePixel();
        break;

    case 'pixel_update':
        $container->settingsController()->updatePixel();
        break;

    case 'domain_create':
        $container->settingsController()->createDomain();
        break;

    case 'domain_delete':
        $container->settingsController()->deleteDomain();
        break;

    case 'domain_verify':
        $container->settingsController()->verifyDomain();
        break;

    case 'utm_store':
        $container->settingsController()->storeUtm();
        break;

    case 'utm_delete':
        $container->settingsController()->deleteUtm();
        break;

    case 'demo_save':
        $container->settingsController()->saveMeta();
        break;

    case 'demo_fetch':
        $container->settingsController()->fetchDemographics();
        break;

    case 'demo_clear':
        $container->settingsController()->clearDemographics();
        break;

    case 'account_password':
        $container->dashboardController()->changePassword();
        break;

    case 'account_deactivate':
        $container->dashboardController()->deactivateAccount();
        break;

    case 'help_pixel':
        (new App\Controller\HelpController())->pixelId();
        break;

    case 'help_index':
        (new App\Controller\HelpController())->index();
        break;

    case 'help_custom_domain':
        (new App\Controller\HelpController())->customDomain();
        break;

    case 'notfound':
    default:
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo App\render('notfound');
        break;
}
