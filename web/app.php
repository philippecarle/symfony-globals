<?php


use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__.'/../app/autoload.php';
include_once __DIR__.'/../var/bootstrap.php.cache';

$env = getenv('SYMFONY_ENV') ?: 'prod';
$debug = (bool)getenv('SYMFONY_DEBUG') ?: false;

if ($debug) {
    // Enable the debug error handler when in debug mode
    Debug::enable();
}

$kernel = new AppKernel($env, $debug);
$kernel->loadClassCache();

$sfRequest = Request::createFromGlobals();

try {
    // Try to handle the request from within Symfony
    $response = $kernel->handle($sfRequest, HttpKernelInterface::MASTER_REQUEST, false);
} catch (NotFoundHttpException $e) {
    // handle legacy
    $legacyHandler = $kernel->getContainer()->get('legacy.handler');

    if (!$response = $legacyHandler->parse($sfRequest)) {
        $legacyHandler->bootLegacy();
        $logger = $kernel->getContainer()->get('logger');
        $kernel->getContainer()->get('session')->save();
        try {
            require_once $legacyHandler->getLegacyPath();
            $response = $legacyHandler->handleResponse();
        } catch (\Exception $e) {
            // In case we have an error in the legacy, we want to be able to
            // have a nice error page instead of a blank page.
            $response = $legacyHandler->handleException($e, $sfRequest);
        }
    }
}

$response->send();
$kernel->terminate($sfRequest, $response);
