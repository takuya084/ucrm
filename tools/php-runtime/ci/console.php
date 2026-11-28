<?php
if (!is_file('/data/ready')) throw new RuntimeException('manual_demo_not_ready');
$app=require __DIR__.'/bootstrap.php';
$kernel=$app->make(\Illuminate\Contracts\Console\Kernel::class);
$input=new \Symfony\Component\Console\Input\ArgvInput;
$status=$kernel->handle($input,new \Symfony\Component\Console\Output\ConsoleOutput);
$kernel->terminate($input,$status);
exit($status);
