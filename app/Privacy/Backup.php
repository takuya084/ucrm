<?php
namespace App\Privacy;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class Backup
{
    public static function create(string $path,bool $offline=false): void {
        if(!$offline&&!app()->environment('testing')&&!app()->isDownForMaintenance())throw new RuntimeException('Stop application/worker writes before backing up (maintenance mode or explicit offline invocation).');
        $parent=realpath(dirname($path));$files=realpath(Files::root());$public=realpath(public_path());
        if(!$parent||$files&&($parent===$files||str_starts_with($parent,$files.DIRECTORY_SEPARATOR))||$public&&($parent===$public||str_starts_with($parent,$public.DIRECTORY_SEPARATOR)))throw new RuntimeException('Backup must be outside public and private-file source directories.');
        if(Migrator::scan()['plaintext']!==0||Files::migrate(true)['plaintext']!==0)throw new RuntimeException('Encrypt legacy database rows/files before creating a backup.');
        DB::connection()->transaction(fn()=>SecretArchive::write($path,self::snapshot()));
        foreach(SecretArchive::records($path) as $record){} // verify every frame and authenticated ending
    }
    private static function snapshot(): \Generator {
        $pdo=DB::connection()->getPdo();$driver=DB::getDriverName();yield ['type'=>'header','version'=>1,'driver'=>$driver];
        if($driver==='sqlite'){
            $schema=$pdo->query("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' AND sql IS NOT NULL ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END,name")->fetchAll(\PDO::FETCH_ASSOC);
        }elseif($driver==='mysql'){
            $schema=[];foreach($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table){$r=$pdo->query('SHOW CREATE TABLE '.Migrator::quote($table))->fetch(\PDO::FETCH_NUM);$schema[]=['type'=>'table','name'=>$table,'sql'=>$r[1]];}
        }else throw new RuntimeException('Unsupported backup database.');
        foreach($schema as $entry)if($entry['type']==='table'){
            yield ['type'=>'schema','sql'=>$entry['sql']];$rows=$pdo->query('SELECT * FROM '.Migrator::quote($entry['name']));
            while($row=$rows->fetch(\PDO::FETCH_ASSOC)){
                // Values are already encrypted; binary SQL values are represented losslessly.
                $encoded=[];foreach($row as $field=>$value)$encoded[$field]=$value===null?null:base64_encode((string)$value);
                yield ['type'=>'row','table'=>$entry['name'],'values'=>$encoded];
            }
        }
        if($driver==='sqlite'&&$pdo->query("SELECT count(*) FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn())foreach($pdo->query('SELECT name,seq FROM sqlite_sequence')->fetchAll(\PDO::FETCH_ASSOC) as $seq)yield ['type'=>'sequence',...$seq];
        foreach($schema as $entry)if($entry['type']!=='table')yield ['type'=>'schema','sql'=>$entry['sql']];
        foreach(Files::paths() as $relative=>$file){
            yield ['type'=>'file','path'=>$relative];$hash=hash_init('sha256');$in=fopen($file,'rb');while(!feof($in)){$chunk=fread($in,65536);if($chunk==='')break;hash_update($hash,$chunk);yield ['type'=>'bytes','value'=>base64_encode($chunk)];}fclose($in);yield ['type'=>'file-end','sha256'=>hash_final($hash)];
        }
    }
    public static function restore(string $path,\PDO $pdo,string $root): void {
        $driver=$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $existing=$driver==='sqlite'?$pdo->query("SELECT count(*) FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' AND type='table'")->fetchColumn():$pdo->query('SHOW TABLES')->fetch();
        if($existing)throw new RuntimeException('Restore requires an empty target database.');
        if(is_link($root)||is_dir($root)&&count(array_diff(scandir($root),['.','..']))>0)throw new RuntimeException('Restore requires an empty private file directory.');
        // Authentication is verified completely before creating a table or file.
        $head=null;foreach(SecretArchive::records($path) as $record){$head??=$record;}
        if(($head['type']??null)!=='header'||($head['driver']??null)!==$driver)throw new RuntimeException('Backup database driver does not match target.');
        if(!is_dir($root)&&!mkdir($root,0700,true))throw new RuntimeException('Cannot create restore directory.');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);$pdo->exec($driver==='sqlite'?'PRAGMA foreign_keys=OFF':'SET FOREIGN_KEY_CHECKS=0');
        if($driver==='sqlite')$pdo->beginTransaction();$file=null;$hash=null;
        try {
            foreach(SecretArchive::records($path) as $record){
                switch($record['type']){
                    case 'header':break;
                    case 'schema':$pdo->exec($record['sql']);break;
                    case 'row':
                        $names=array_keys($record['values']);$quote=fn($s)=>$driver==='mysql'?'`'.str_replace('`','``',$s).'`':'"'.str_replace('"','""',$s).'"';
                        $sql='INSERT INTO '.$quote($record['table']).'('.implode(',',array_map($quote,$names)).') VALUES('.implode(',',array_fill(0,count($names),'?')).')';
                        $values=array_map(fn($v)=>$v===null?null:base64_decode($v,true),array_values($record['values']));$pdo->prepare($sql)->execute($values);break;
                    case 'sequence':$pdo->prepare('DELETE FROM sqlite_sequence WHERE name=?')->execute([$record['name']]);$pdo->prepare('INSERT INTO sqlite_sequence(name,seq) VALUES(?,?)')->execute([$record['name'],$record['seq']]);break;
                    case 'file':
                        $relative=$record['path'];if(str_starts_with($relative,'/')||str_contains($relative,'\\')||in_array('..',explode('/',$relative),true)||str_contains($relative,"\0"))throw new RuntimeException('Invalid restored file path.');
                        $target=$root.'/'.$relative;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);$file=fopen($target,'xb');chmod($target,0600);$hash=hash_init('sha256');break;
                    case 'bytes':$bytes=base64_decode($record['value'],true);if(!$file||$bytes===false)throw new RuntimeException('Invalid restored bytes.');if(fwrite($file,$bytes)!==strlen($bytes))throw new RuntimeException('Restore write failed.');hash_update($hash,$bytes);break;
                    case 'file-end':if(!$file)throw new RuntimeException('Invalid file ending.');fclose($file);$file=null;if(hash_final($hash)!==$record['sha256'])throw new RuntimeException('Restored file hash mismatch.');break;
                    default:throw new RuntimeException('Unknown backup record.');
                }
            }
            if($file)throw new RuntimeException('Unfinished restored file.');
            if($driver==='sqlite'){
                if($pdo->query('PRAGMA integrity_check')->fetchColumn()!=='ok'||$pdo->query('PRAGMA foreign_key_check')->fetch()!==false)throw new RuntimeException('Restored database integrity failed.');$pdo->commit();
            }
        }catch(\Throwable $e){if($file)fclose($file);if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        finally{$pdo->exec($driver==='sqlite'?'PRAGMA foreign_keys=ON':'SET FOREIGN_KEY_CHECKS=1');}
    }
}
