<?php

use Pimcore\Tests\Util\Autoloader;

define('PIMCORE_PROJECT_ROOT', realpath(__DIR__ . '/..'));

// set the used pimcore/symfony environment
putenv('PIMCORE_ENVIRONMENT=test');

include PIMCORE_PROJECT_ROOT . '/vendor/autoload.php';

\Pimcore\Bootstrap::setProjectRoot();
\Pimcore\Bootstrap::boostrap();

require_once PIMCORE_PROJECT_ROOT . '/vendor/pimcore/pimcore/tests/_support/Util/Autoloader.php';

Autoloader::addNamespace('Pimcore\Tests', PIMCORE_PROJECT_ROOT . '/vendor/pimcore/pimcore/tests/_support');
Autoloader::addNamespace('Pimcore\Tests\Unit', __DIR__ . '/unit');
