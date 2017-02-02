<?php

///////////////////////////////////////////////////////////////////////////////////////////////////
// INCLUDE STUFF
///////////////////////////////////////////////////////////////////////////////////////////////////

require 'sessionStart.inc.php';

require '../vendor/autoload.php';

require 'database.inc.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

///////////////////////////////////////////////////////////////////////////////////////////////////
// INITIAL CONFIG STUFF
///////////////////////////////////////////////////////////////////////////////////////////////////
$pages = array(
    '404',
    '503',
    'about',
    'home',
    'javadoc',
    'layout',
    'releases',
    'wiki'
);

$db = new OtflDatabase();
$db->init();

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();

$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('../templates');

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));

    return $view;
};



///////////////////////////////////////////////////////////////////////////////////////////////////
// PAGE RULE STUFF
///////////////////////////////////////////////////////////////////////////////////////////////////

$app->add(function (Request $request, Response $response, callable $next) use ($db) {
    $response = $next($request, $response);

    $init = $db->pageInit($request, $response);
    if ($init != false) {
        $response = $init;
    }

    return $response;
});

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html');
});

$app->post('/login', function (Request $request, Response $response) use ($db) {
    if ($db->loggedIn()) {
        return $response->withHeader('Location', '/home');
    }

    $data = $request->getParsedBody();
    $errors = array();
    $error = '';
    $unameValue = isset($data['username']) && $data['username'] != null ? $data['username'] : '';
    $rememberMe = isset($data['remember']) ? 'checked' : '';

    if (isset($data['submit'])) {
        $e = false;
        if (!isset($data['username']) || (isset($data['username']) && $data['username'] == '')) {
            $errors[] = 'You must put in your username';
            $e = true;
        }

        if (!isset($data['password']) || (isset($data['password']) && $data['password'] == '')) {
            $errors[] = 'You must put in your password';
            $e = true;
        }

        if (!$e) {
            /* @var $username string */
            $username = $data['username'];
            /* @var $password string */
            $password = $data['password'];
            $login = $db->login($username, $password, $request, $response, isset($data['remember']));

            if (is_array($login)) {
                $errors = array_merge($errors, $login);
            } else {
                $response = $login;
            }
        }
    }

    if (!empty($errors)) {
        $error = '<div class="alert alert-danger" role="alert"><h4 class="alert-heading">There were some errors</h4><p>';
        foreach ($errors as $k => $v) {
            $error .= $v . '<br />';
        }
        $error .= '</p></div>';
    }

    return $this->view->render($response, 'login.html', ['usernameValue' => $unameValue, 'rememberMeValue' => $rememberMe, 'errors' => $error]);
})->setName('login');

$app->get('/login', function (Request $request, Response $response) use ($db) {
    if ($db->loggedIn()) {
        return $response->withHeader('Location', '/home');
    }

    return $this->view->render($response, 'login.html');
});

$app->get('/logout', function (Request $request, Response $response) use ($db) {
    if (!$db->loggedIn()) {
        return $response->withStatus(503)->withHeader('Location', '/home');
    }

    while (isset($_SESSION['id'])) {
        unset($_SESSION['id']);
    }

    $response = $db->removeCookies($response);

    return $response->withHeader('Location', '/home');
})->setName('logout');

$app->get('/{page}', function (Request $request, Response $response) use ($pages, $db) {
    $page = $request->getAttribute("page");
    if (!in_array($page, $pages)) {
        return $response->withStatus(404)->withHeader("Location", "/404");
    }

    $sidebar = $db->createSidebar($this);

    return $this->view->render($response, $page . ".html", ['sidebar' => $sidebar]);
})->setName("page");

$app->run();
