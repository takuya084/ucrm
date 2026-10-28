<?php

// CI-only entry point; production bootstrap/routes never load this file.
$root = getenv('RUNNER_TEMP');
$dir = getenv('BROWSER_FIXTURE_DIR');
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('SAFE_BROWSER_CI') !== '1'
    || !is_string($root) || !is_string($dir)
    || !preg_match('~^'.preg_quote($root, '~').'/ucrm-browser-[A-Za-z0-9]+$~D', $dir)
    || realpath($dir) !== $dir || is_link($dir)
    || (fileperms($dir) & 0777) !== 0700 || fileowner($dir) !== posix_geteuid()
    || !is_file($dir.'/database.sqlite') || is_link($dir.'/database.sqlite')
    || fileowner($dir.'/database.sqlite') !== posix_geteuid()
    || is_file(__DIR__.'/../bootstrap/cache/config.php')) {
    throw new RuntimeException('browser_fixture_refused');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->useEnvironmentPath(__DIR__.'/../tests/fixtures');
$app->loadEnvironmentFrom('environment');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    function ($app) use ($dir): void {
        $app->make('config')->set([
            'app.env'=>'testing', 'app.debug'=>false,
            'app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.url'=>'http://127.0.0.1:8765',
            'database.default'=>'sqlite',
            'database.connections'=>['sqlite'=>[
                'driver'=>'sqlite', 'database'=>$dir.'/database.sqlite',
                'prefix'=>'', 'foreign_key_constraints'=>true,
            ]],
            'database.redis'=>[], 'cache.default'=>'array',
            'session.driver'=>'file', 'session.files'=>$dir.'/sessions',
            'session.domain'=>null, 'session.secure'=>false,
            'mail.default'=>'array', 'queue.default'=>'sync',
            'queue.connections'=>['sync'=>['driver'=>'sync']],
            'logging.default'=>'null',
            'services.openai.api_key'=>null,
            'services.houkago_plus.api_token'=>null,
            'services.houkago_plus.base_url'=>'http://127.0.0.1:1',
            'services.stripe.pb_key'=>'pk_test_fixture',
            'services.stripe.st_key'=>'sk_test_fixture',
            'services.stripe.price_id'=>'price_fixture',
            'services.stripe.webhook_secret'=>'whsec_fixture',
        ]);
    });
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Http::preventStrayRequests();
\Illuminate\Support\Facades\Mail::fake();
\Illuminate\Support\Facades\Notification::fake();
\Illuminate\Support\Facades\Queue::fake();
\Stripe\ApiRequestor::setHttpClient(new \Tests\Support\DenyStripeHttpClient());
return $app;
