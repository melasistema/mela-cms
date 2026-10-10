<?php

namespace Melasistema\MelaCms\Tests;

use Melasistema\MelaCms\MelaCmsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MelaCmsServiceProvider::class,
        ];
    }
}
