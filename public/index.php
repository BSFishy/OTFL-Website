<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

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

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html');
});

$app->get("/{page}", function (Request $request, Response $response) {
    return $this->view->render($response, $request->getAttribute("page") . ".html");
})->setName("page");

$app->run();