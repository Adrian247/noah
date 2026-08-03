<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static bool $testDatabaseReady = false;

    /**
     * Aísla la suite de la base de desarrollo.
     *
     * En Docker el contenedor exporta `DB_CONNECTION=pgsql` y `DB_DATABASE=phoenix` como variables
     * reales del proceso; `$_SERVER` gana sobre lo que declare `phpunit.xml`, incluso con `force`.
     * Sin esta salvaguarda la suite corre contra la base de desarrollo y `RefreshDatabase` la deja
     * vacía, obligando a volver a sembrar la demo después de cada `php artisan test`.
     *
     * Las migraciones requieren PostgreSQL, así que no se cambia de motor: se redirige a una base
     * hermana `<nombre>_testing`, que se crea sola la primera vez.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $this->isolateTestDatabase($app);

        return $app;
    }

    private function isolateTestDatabase(Application $app): void
    {
        $config = $app['config'];

        if ($config->get('database.default') !== 'pgsql') {
            return;
        }

        $current = (string) $config->get('database.connections.pgsql.database');

        if ($current === '' || str_ends_with($current, '_testing')) {
            return;
        }

        $target = $current.'_testing';

        $this->ensureDatabaseExists($app, $current, $target);

        $config->set('database.connections.pgsql.database', $target);
        $config->set('database.connections.pgsql.url', null);
        $app['db']->purge('pgsql');
    }

    private function ensureDatabaseExists(Application $app, string $maintenance, string $target): void
    {
        if (self::$testDatabaseReady) {
            return;
        }

        self::$testDatabaseReady = true;

        $config = $app['config'];
        $config->set('database.connections.pgsql_maintenance', array_merge(
            (array) $config->get('database.connections.pgsql'),
            ['database' => $maintenance, 'url' => null],
        ));

        $connection = $app['db']->connection('pgsql_maintenance');

        if ($connection->selectOne('select 1 from pg_database where datname = ?', [$target]) === null) {
            $connection->statement('create database "'.$target.'"');
        }

        $app['db']->purge('pgsql_maintenance');
    }
}
