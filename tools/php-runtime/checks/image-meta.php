<?php
if(PHP_SAPI!=='cli'||@file_get_contents('/opt/manual-demo-marker')!=='local-manual-demo-v1'||is_file('/app/.env')||is_file('/app/bootstrap/cache/config.php'))throw new RuntimeException('Demo image required.');
$hashes=[];
foreach(['/app','/demo'] as $root){
    $files=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $file){
        $path=$file->getPathname();$relative=substr($path,strlen($root)+1);
        $files[$relative]=$file->isLink()?['link',readlink($path)]:($file->isFile()?['file',hash_file('sha256',$path)]:['directory']);
    }
    ksort($files,SORT_STRING);$hashes[$root]=hash('sha256',json_encode($files,JSON_THROW_ON_ERROR));
}
$extensions=get_loaded_extensions();sort($extensions,SORT_STRING);
$ini=[];foreach(['memory_limit','upload_max_filesize','post_max_size','default_charset','allow_url_fopen','serialize_precision','max_execution_time'] as $name)$ini[$name]=ini_get($name);
echo json_encode(['php'=>PHP_VERSION,'extensions'=>$extensions,'gd'=>function_exists('gd_info')?gd_info():[],'ini'=>$ini,'hashes'=>$hashes],JSON_THROW_ON_ERROR)."\n";
