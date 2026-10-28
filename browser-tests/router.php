<?php

$app = require __DIR__.'/bootstrap.php';
$request = \Illuminate\Http\Request::capture();
$path = $request->getPathInfo();
// Only built assets and fixed public image filenames; never return arbitrary files.
if (preg_match('~^/(build/assets/[A-Za-z0-9_.-]+\.(?:js|css)|[A-Za-z0-9_-]+\.(?:png|ico|svg))$~D', $path)) {
    $file = __DIR__.'/../public'.$path;
    if (is_file($file) && !is_link($file)) {
        header('Content-Type: '.match (pathinfo($file, PATHINFO_EXTENSION)) {
            'js'=>'application/javascript', 'css'=>'text/css',
            'png'=>'image/png', 'svg'=>'image/svg+xml', default=>'image/x-icon',
        });
        readfile($file);
        return;
    }
}
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
