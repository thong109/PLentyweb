<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Tests\ServiceProvider;

use Eccube\Tests\Mock\CsrfTokenManagerMock;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;

/**
 * CsrfMockServiceProvider
 *
 * @author Kentaro Ohkouchi
 */
class CsrfMockServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function register(Container $app)
    {
        $app['csrf.token_manager'] = function () {
            return new CsrfTokenManagerMock();
        };
    }

    public function boot(Application $app)
    {
        // quiet
    }
}
