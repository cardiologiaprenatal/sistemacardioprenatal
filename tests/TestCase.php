<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceSqliteTestingEnvironment();

        $app = parent::createApplication();

        if ($app['config']->get('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'Testes recusaram a conexão '.$app['config']->get('database.default').'. Use sqlite (:memory:).'
            );
        }

        return $app;
    }

    /**
     * php artisan test no Docker herda DB_CONNECTION=mysql do .env.
     * Sem isso, RefreshDatabase apaga o MySQL da aplicação.
     */
    private function forceSqliteTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
