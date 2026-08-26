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

    case 'notfound':
    default:
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo App\render('notfound');
        break;
}
