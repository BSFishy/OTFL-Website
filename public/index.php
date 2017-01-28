<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();

$container['view'] = new \Slim\Views\PhpRenderer("../templates/");

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.phtml');
});

$app->run();