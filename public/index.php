<?php

///////////////////////////////////////////////////////////////////////////////////////////////////
// INCLUDE STUFF
///////////////////////////////////////////////////////////////////////////////////////////////////

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

require 'sessionStart.inc.php';
require 'database.inc.php';

///////////////////////////////////////////////////////////////////////////////////////////////////
// INITIAL CONFIG STUFF
///////////////////////////////////////////////////////////////////////////////////////////////////

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

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html');
});

//$app->get("/{page}", function (Request $request, Response $response) {
//    return $this->view->render($response, $request->getAttribute("page") . ".html");
//})->setName("page");

$app->get('/{page}', function (Request $request, Response $response) {
    $pages = array(
        '404',
        '503',
        'about',
        'home',
        'javadoc',
        'layout',
        'login',
        'releases',
        'wiki'
    );

    $page = $request->getAttribute("page");
    if (!in_array($page, $pages)) {
        return $response->withStatus(404)->withHeader("Location", "/404");
    }

    return $this->view->render($response, $request->getAttribute("page") . ".html");
})->setName("page");

$app->run();
