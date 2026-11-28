<?php
// Browser requests to CDNs, Stripe and other external origins are blocked too.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-src 'none'; object-src 'none'; form-action 'self'; base-uri 'self'");
if (!is_file('/data/ready')) { http_response_code(503); exit('Demo setup incomplete'); }
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if (preg_match('~^/(workspace\.css|input-clipboard\.js|absence-board/board\.(?:js|css)|transport-grid/grid\.(?:js|css)|build/assets/[A-Za-z0-9_.-]+\.(?:js|css)|(?:logo/)?[A-Za-z0-9_-]+\.(?:png|ico|svg))$~D', $path)) {
    $file='/app/public'.$path;
    if (is_file($file) && !is_link($file)) {
        header('Content-Type: '.match(pathinfo($file,PATHINFO_EXTENSION)) {
            'js'=>'application/javascript','css'=>'text/css','png'=>'image/png','svg'=>'image/svg+xml',default=>'image/x-icon',
        });
        readfile($file); return;
    }
}
// The built-in server can treat an existing asset as SCRIPT_NAME. Handle assets
// above before Laravel, and make the dynamic front controller unambiguous.
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['SCRIPT_FILENAME']='/app/public/index.php';
$_SERVER['PHP_SELF']='/index.php';
unset($_SERVER['PATH_INFO']);
$app = require __DIR__.'/bootstrap.php';
$request = \Illuminate\Http\Request::capture();
if ($path === '/demo-health') { header('Content-Type: application/json'); echo '{"demo":true}'; return; }
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
$response=$kernel->handle($request);
$response->send();
$kernel->terminate($request,$response);

