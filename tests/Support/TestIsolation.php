<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

final class TestIsolation
{
    public static function bootstrap(Application $app): void
    {
        if ($app->configurationIsCached()) throw new \RuntimeException('test_cached_config_refused');
        $app->useEnvironmentPath(__DIR__.'/../fixtures');
        $app->loadEnvironmentFrom('environment');
        $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
            $c = $app->make('config');
            if ($c->get('app.env') !== 'testing' || $c->get('database.default') !== 'sqlite'
                || $c->get('database.connections.sqlite.database') !== ':memory:'
                || !in_array($c->get('database.connections.sqlite.url'), [null,''], true)
                || !in_array(getenv('DATABASE_URL'), [false,''], true)
                || !in_array(getenv('DB_URL'), [false,''], true)) {
                throw new \RuntimeException('test_database_refused');
            }
            $sqlite = $c->get('database.connections.sqlite');
            $c->set([
                'database.connections'=>['sqlite'=>$sqlite], 'database.redis'=>[],
                'cache.default'=>'array', 'session.driver'=>'array', 'mail.default'=>'array',
                'queue.default'=>'sync', 'queue.connections'=>['sync'=>['driver'=>'sync']],
                'services.openai.api_key'=>null, 'services.houkago_plus.api_token'=>null,
                'services.houkago_plus.base_url'=>'http://127.0.0.1:1',
            ]);
        });
    }
}
