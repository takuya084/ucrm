<?php
namespace App\Privacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class Migrator
{
    public static function prepare(): void {
        Cipher::key('key');Cipher::key('index_key');
        $pdo=DB::connection()->getPdo();$sqlite=DB::getDriverName()==='sqlite';
        if($sqlite){$pdo->exec('PRAGMA foreign_keys=OFF');DB::beginTransaction();}
        try {foreach(Registry::tables() as $table=>$fields) {
            if(!Schema::hasTable($table))continue;
            $columns=Schema::getColumnListing($table);$fields=array_intersect_key($fields,array_flip($columns));
            if(!$fields)continue;
            $indexes=self::indexes($table);$changed=[];
            foreach($indexes as $index) {
                if(!array_intersect($index['columns'],array_keys($fields)))continue;
                if(!$sqlite){self::supportForeignIndex($table,$index,$fields);Schema::table($table,function(Blueprint $t)use($index){
                    if($index['primary'])$t->dropPrimary();elseif($index['unique'])$t->dropUnique($index['name']);else$t->dropIndex($index['name']);
                });}$changed[]=$index;
            }
            Schema::table($table,function(Blueprint $t)use($fields,$columns){foreach($fields as $column=>$type)if(!in_array(Registry::indexColumn($column),$columns,true))$t->char(Registry::indexColumn($column),64)->nullable();});
            if($sqlite)self::rebuildSqlite($table,$fields,$changed);
            else Schema::table($table,function(Blueprint $t)use($fields){foreach($fields as $column=>$type)$t->longText($column)->nullable()->default(null)->change();});
            $primary=[];foreach($indexes as $index)if($index['primary']){$primary=$index['columns'];break;}self::encryptTable($table,array_keys($fields),false,$primary);
            foreach($changed as $index) {
                if($sqlite&&($index['primary']||str_starts_with($index['name'],'sqlite_autoindex_')))continue;
                $new=array_map(fn($c)=>isset($fields[$c])?Registry::indexColumn($c):$c,$index['columns']);
                Schema::table($table,function(Blueprint $t)use($index,$new){if($index['primary'])$t->primary($new);elseif($index['unique'])$t->unique($new,$index['name']);else$t->index($new,$index['name']);});
            }
        }
        if($sqlite){if($pdo->query('PRAGMA foreign_key_check')->fetch()!==false)throw new RuntimeException('Migration foreign key verification failed.');DB::commit();}
        }catch(\Throwable $e){if($sqlite&&DB::transactionLevel())DB::rollBack();throw $e;}
        finally{if($sqlite)$pdo->exec('PRAGMA foreign_keys=ON');}
    }

    private static function supportForeignIndex(string $table,array $index,array $fields): void {
        $prefix=[];foreach($index['columns'] as $column){if(isset($fields[$column]))break;$prefix[]=$column;}
        if(!$prefix)return;$name='privacy_fk_'.substr(hash('sha256',$table.'.'.implode('.',$prefix)),0,24);
        foreach(self::indexes($table) as $existing)if($existing['name']===$name)return;
        Schema::table($table,fn(Blueprint $t)=>$t->index($prefix,$name));
    }
    private static function rebuildSqlite(string $table,array $fields,array $changed): void {
        $pdo=DB::connection()->getPdo();$q=self::quote($table);
        $statement=$pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");$statement->execute([$table]);$original=$statement->fetchColumn();$statement->closeCursor();
        $statement=$pdo->prepare("SELECT name,sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");$statement->execute([$table]);$objects=$statement->fetchAll(\PDO::FETCH_ASSOC);
        $seq=null;if($pdo->query("SELECT count(*) FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn()){ $st=$pdo->prepare('SELECT seq FROM sqlite_sequence WHERE name=?');$st->execute([$table]);$v=$st->fetchColumn();$st->closeCursor();if($v!==false)$seq=$v;}
        $body=substr($original,strpos($original,'(')+1);$body=substr($body,0,strrpos($body,')'));$parts=[];$part='';$depth=0;$quote=null;
        for($i=0;$i<strlen($body);$i++){
            $ch=$body[$i];
            if($quote!==null){$part.=$ch;if($ch===$quote){if(($body[$i+1]??null)===$quote){$part.=$body[++$i];}else$quote=null;}continue;}
            if(in_array($ch,["'",'"','`'],true)){$quote=$ch;$part.=$ch;continue;}
            if($ch==='(')$depth++;elseif($ch===')')$depth--;
            if($ch===','&&$depth===0){$parts[]=trim($part);$part='';}else$part.=$ch;
        }$parts[]=trim($part);
        foreach($parts as &$definition){
            if(preg_match('/^["`\[]?([a-z_][a-z0-9_]*)["`\]]?\s+/i',$definition,$m)&&isset($fields[$m[1]])){
                $definition=self::quote($m[1]).' TEXT'.(preg_match('/\bNOT NULL\b/i',$definition)?' NOT NULL':'');
            }elseif(preg_match('/^(?:constraint\s+\S+\s+)?(?:primary key|unique)\s*\(/i',$definition)){
                foreach(array_keys($fields) as $column)$definition=preg_replace('/(["`])'.preg_quote($column,'/').'\1/', '"'.Registry::indexColumn($column).'"',$definition);
            }
        }unset($definition);
        $temp='privacy_rebuild_'.substr(hash('sha256',$table),0,16);$columns=Schema::getColumnListing($table);$names=implode(',',array_map([self::class,'quote'],$columns));
        $pdo->exec('CREATE TABLE '.self::quote($temp).' ('.implode(',',$parts).')');
        $pdo->exec('INSERT INTO '.self::quote($temp).' ('.$names.') SELECT '.$names.' FROM '.$q);
        $pdo->exec('DROP TABLE '.$q);$pdo->exec('ALTER TABLE '.self::quote($temp).' RENAME TO '.$q);
        if($seq!==null){$pdo->prepare('DELETE FROM sqlite_sequence WHERE name=?')->execute([$table]);$pdo->prepare('INSERT INTO sqlite_sequence(name,seq) VALUES(?,?)')->execute([$table,$seq]);}
        $excluded=array_column($changed,'name');foreach($objects as $object)if(!in_array($object['name'],$excluded,true))$pdo->exec($object['sql']);
    }
    private static function indexes(string $table): array {
        $pdo=DB::connection()->getPdo();$driver=DB::getDriverName();$out=[];
        if($driver==='sqlite') {
            $columns=$pdo->query('PRAGMA table_info('.self::quote($table).')')->fetchAll(\PDO::FETCH_ASSOC);
            $primary=array_column(array_filter($columns,fn($c)=>$c['pk']>0),'name');
            if($primary)$out[]=['name'=>'primary','primary'=>true,'unique'=>true,'columns'=>$primary];
            foreach($pdo->query('PRAGMA index_list('.self::quote($table).')')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if(($row['origin']??'')==='pk')continue;
                $names=array_column($pdo->query('PRAGMA index_info('.self::quote($row['name']).')')->fetchAll(\PDO::FETCH_ASSOC),'name');
                $out[]=['name'=>$row['name'],'primary'=>false,'unique'=>(bool)$row['unique'],'columns'=>$names];
            }
        } elseif($driver==='mysql') {
            foreach($pdo->query('SHOW INDEX FROM '.self::quote($table))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $name=$row['Key_name'];$out[$name]??=['name'=>$name,'primary'=>$name==='PRIMARY','unique'=>!(bool)$row['Non_unique'],'columns'=>[]];$out[$name]['columns'][]=$row['Column_name'];
            }
        } else throw new RuntimeException('Privacy migration supports SQLite and MySQL only.');
        return array_values($out);
    }
    public static function quote(string $name): string {return DB::connection()->getQueryGrammar()->wrap($name);}
    public static function encryptTable(string $table,array $columns,bool $dry=false,array $originalKeys=[]): array {
        $pdo=DB::connection()->getPdo();$sqlite=DB::getDriverName()==='sqlite';$names=Schema::getColumnListing($table);
        $keys=$originalKeys;foreach(($keys?[]:self::indexes($table)) as $index)if($index['primary']){$keys=$index['columns'];break;}
        $rows=$pdo->query('SELECT '.($sqlite?'rowid AS privacy_rowid,':'').'* FROM '.self::quote($table))->fetchAll(\PDO::FETCH_ASSOC);
        $counts=['encrypted'=>0,'verified'=>0];
        foreach($rows as $row) {
            $updates=[];
            foreach($columns as $column) {
                $raw=$row[$column]??null;
                if($raw===null){$updates[Registry::indexColumn($column)]=null;continue;}
                if(is_string($raw)&&str_starts_with($raw,Cipher::PREFIX)){$plain=Cipher::decrypt($table.'.'.$column,$raw);$counts['verified']++;}
                else {
                    $plain=$raw;
                    if($table==='children'&&in_array($column,['disability_note','allergy_note','care_note'],true)) {
                        $candidate=json_decode(base64_decode((string)$raw,true)?:'',true);
                        if(is_array($candidate)&&isset($candidate['iv'],$candidate['value'])) {
                            try {$plain=\Illuminate\Support\Facades\Crypt::decryptString($raw);}catch(\Throwable){throw new RuntimeException('Legacy child-data encryption key does not match.');}
                        }
                    }
                    $plain=Registry::normalize($table,$column,$plain);
                    $updates[$column]=Cipher::encrypt($table.'.'.$column,$plain);$counts['encrypted']++;
                }
                $updates[Registry::indexColumn($column)]=Cipher::index($table.'.'.$column,$plain);
            }
            if($dry||!$updates)continue;
            $identities=$sqlite?['rowid'=>$row['privacy_rowid']]:($keys?array_intersect_key($row,array_flip($keys)):$row);
            $sql='UPDATE '.self::quote($table).' SET '.implode(',',array_map(fn($c)=>self::quote($c).'=?',array_keys($updates))).' WHERE '.implode(' AND ',array_map(fn($c)=>!$sqlite&&!$keys?'BINARY '.self::quote($c).' <=> BINARY ?':self::quote($c).'=?',array_keys($identities))).(!$sqlite&&!$keys?' LIMIT 1':'');
            $statement=$pdo->prepare($sql);$statement->execute([...array_values($updates),...array_values($identities)]);
        }
        return $counts;
    }
    public static function scan(bool $rewrite=false,bool $dry=false): array {
        Cipher::key('key');Cipher::key('index_key');$result=['encrypted'=>0,'verified'=>0,'plaintext'=>0];
        foreach(Registry::tables() as $table=>$fields) {
            if(!Schema::hasTable($table))continue;
            $columns=array_intersect(array_keys($fields),Schema::getColumnListing($table));
            if(!$columns)continue;
            if($rewrite){$counts=self::encryptTable($table,$columns,$dry);$result['encrypted']+=$counts['encrypted'];$result['verified']+=$counts['verified'];continue;}
            $selected=array_merge($columns,array_map([Registry::class,'indexColumn'],$columns));$rows=DB::connection()->getPdo()->query('SELECT '.implode(',',array_map([self::class,'quote'],$selected)).' FROM '.self::quote($table));
            while($row=$rows->fetch(\PDO::FETCH_ASSOC))foreach($columns as $column){$value=$row[$column];if($value===null){if($row[Registry::indexColumn($column)]!==null)throw new RuntimeException('Private lookup verification failed: '.$table.'.'.$column);continue;}
                if(is_string($value)&&str_starts_with($value,Cipher::PREFIX)){$plain=Cipher::decrypt($table.'.'.$column,$value);if(!hash_equals(Cipher::index($table.'.'.$column,$plain),(string)$row[Registry::indexColumn($column)]))throw new RuntimeException('Private lookup verification failed: '.$table.'.'.$column);$result['verified']++;}else$result['plaintext']++;
            }
        }
        return $result;
    }
}
