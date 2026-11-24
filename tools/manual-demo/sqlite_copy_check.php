<?php
// Only disposable copies are opened by SQLite. Original source files are never opened as a DB.
function shift_copy_snapshot(string $path): array {
    $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout=10000');$pdo->exec('PRAGMA query_only=ON');
    if ($pdo->query('PRAGMA integrity_check')->fetchColumn()!=='ok' || $pdo->query('PRAGMA foreign_key_check')->fetch()!==false) throw new RuntimeException('private_copy_integrity_failed');
    $result=['schema'=>$pdo->query("SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll(PDO::FETCH_NUM),'tables'=>[]];
    $names=$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($names as $name) {
        $hash=hash_init('sha256');$count=0;
        foreach ($pdo->query('SELECT * FROM "'.str_replace('"','""',$name).'" ORDER BY rowid',PDO::FETCH_NUM) as $row) {
            hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;
        }
        $result['tables'][$name]=['rows'=>$count,'sha256'=>hash_final($hash)];
    }
    return $result;
}
function shift_verify_sqlite_copy(array $config): array {
    $source=$config['source'];$workspace=$config['workspace'];$reference=$config['reference'];$expected=$config['expected'];
    if (!is_dir($source) || is_link($source) || file_exists($workspace) || is_link($workspace)) throw new RuntimeException('invalid_private_copy_location');
    foreach ($expected as $name=>$sha) {
        if (!is_string($name) || basename($name)!==$name || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D',$sha)) throw new RuntimeException('invalid_copy_parameters');
        $path=$source.'/'.$name;
        if (!is_file($path) || is_link($path) || hash_file('sha256',$path)!==$sha) throw new RuntimeException('source_file_mismatch: '.$name);
    }
    if (!isset($expected['database.sqlite'],$expected[$reference])) throw new RuntimeException('database_and_reference_required');
    if (!mkdir($workspace,0700) || !mkdir($workspace.'/live',0700) || !mkdir($workspace.'/reference',0700)) throw new RuntimeException('private_copy_directory_failed');
    $files=['database.sqlite'=>$workspace.'/live/database.sqlite',$reference=>$workspace.'/reference/database.sqlite'];
    if (isset($expected['database.sqlite-wal'])) $files['database.sqlite-wal']=$workspace.'/live/database.sqlite-wal';
    foreach ($files as $name=>$destination) {
        if (!copy($source.'/'.$name,$destination)) throw new RuntimeException('private_copy_failed: '.$name);
        chmod($destination,0600);
        if (hash_file('sha256',$destination)!==$expected[$name]) throw new RuntimeException('copied_file_mismatch: '.$name);
    }
    $actual=shift_copy_snapshot($workspace.'/live/database.sqlite');
    $saved=shift_copy_snapshot($workspace.'/reference/database.sqlite');
    if ($actual!==$saved) throw new RuntimeException('live_database_differs_from_verified_snapshot');
    foreach ($expected as $name=>$sha) if (hash_file('sha256',$source.'/'.$name)!==$sha) throw new RuntimeException('source_changed_during_verification');
    return ['ok'=>true,'tables'=>count($actual['tables']),'snapshot_sha256'=>hash('sha256',json_encode($actual,JSON_THROW_ON_ERROR))];
}
