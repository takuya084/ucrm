<?php
// Separate entry point. Production bootstrap/routes never include this harness.
$kind = getenv('DEMO_APP');
if (getenv('MANUAL_DEMO') !== '1' || !in_array($kind, ['p', 'u'], true)
    || @file_get_contents('/opt/manual-demo-marker') !== 'local-manual-demo-v1'
    || is_file('/app/.env') || is_file('/app/bootstrap/cache/config.php')
    || !is_file('/data/database.sqlite') || is_link('/data/database.sqlite')) {
    throw new RuntimeException('manual_demo_refused');
}
$loader=require '/app/vendor/autoload.php';
$loader->setClassMapAuthoritative(false);
require_once __DIR__.'/SqliteConnection.php';
// Only this isolated demo uses bounded retries for SQLite writer conflicts.
\Illuminate\Database\Connection::resolverFor('sqlite', static fn ($pdo, $database, $prefix, $config) =>
    new \ManualDemo\SqliteConnection($pdo, $database, $prefix, $config));
if(!is_string(getenv('APP_KEY'))||!preg_match('/^base64:[A-Za-z0-9+\/]{43}=$/D',getenv('APP_KEY')))throw new RuntimeException('manual_private_key_required');
$app = require '/app/bootstrap/app.php';
$app->useEnvironmentPath('/empty-env');
$app->loadEnvironmentFrom('no-environment');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($kind) {
    // Keep providers' environment checks aligned with the fixed local profile.
    $app->detectEnvironment(static fn () => 'local');
    $app->make('config')->set([
        'app.env'=>'local', 'app.debug'=>false, 'app.name'=>'【ダミーデータ】'.($kind==='p'?'予約':'業務管理'),
        'app.timezone'=>'Asia/Tokyo',
        'app.key'=>getenv('APP_KEY'),
        'app.url'=>$kind==='p'?'http://127.0.0.1:18081':'http://localhost:18082',
        'database.default'=>'sqlite', 'database.connections'=>['sqlite'=>[
            'driver'=>'sqlite', 'database'=>'/data/database.sqlite', 'prefix'=>'',
            'foreign_key_constraints'=>true, 'busy_timeout'=>5000,
            'options'=>[\PDO::ATTR_TIMEOUT=>5],
        ]], 'database.redis'=>[],
        'session.driver'=>'file', 'session.files'=>'/app/storage/framework/sessions',
        'session.cookie'=>'manual_demo_'.$kind, 'session.domain'=>null, 'session.secure'=>false,
        'cache.default'=>'file', 'cache.stores.file'=>['driver'=>'file',
            'path'=>'/app/storage/framework/cache','lock_path'=>'/app/storage/framework/cache'],
        'mail.default'=>'array', 'logging.default'=>'stderr',
        'queue.default'=>'database', 'queue.connections'=>['database'=>[
            'driver'=>'database','connection'=>'sqlite','table'=>'jobs','queue'=>'default',
            'retry_after'=>90,'after_commit'=>false,
        ]], 'queue.failed'=>['driver'=>'database-uuids','database'=>'sqlite','table'=>'failed_jobs'],
        'services.houkago_plus.base_url'=>'http://p:8000', 'services.houkago_plus.api_token'=>null,
        'services.openai.api_key'=>null,
        'services.stripe.pb_key'=>'pk_test_manual_demo', 'services.stripe.st_key'=>'sk_test_manual_demo',
        'services.stripe.price_id'=>'price_manual_demo', 'services.stripe.webhook_secret'=>'whsec_manual_demo',
    ]);
});
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Tokyo');
// Keep actual auth, CSRF, HTTP integration and database queues. Deliver no email.
\Illuminate\Support\Facades\Mail::fake();
\Illuminate\Support\Facades\Notification::fake();
return $app;
