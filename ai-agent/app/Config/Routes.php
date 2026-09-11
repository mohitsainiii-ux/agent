<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Chat');      // Set Chat as default controller
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Chat::index');

$routes->get('auth/current', 'Auth::current');
$routes->post('auth/register', 'Auth::register');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/logout', 'Auth::logout');

$routes->post('chat', 'Chat::send');
$routes->get('chat/conversations', 'Chat::conversations');
$routes->post('chat/conversations', 'Chat::createConversation');
$routes->get('chat/conversations/(:num)', 'Chat::loadConversation/$1');
$routes->delete('chat/conversations/(:num)', 'Chat::deleteConversation/$1');
$routes->post('chat/conversations/(:num)/messages', 'Chat::sendMessage/$1');
$routes->get('numpy/health', 'Numpy::health');
$routes->post('numpy/process', 'Numpy::process');

$routes->get('settings', 'Settings::index');
$routes->post('settings', 'Settings::save');
$routes->post('settings/test', 'Settings::test');