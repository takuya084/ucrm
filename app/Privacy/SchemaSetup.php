<?php
namespace App\Privacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SchemaSetup
{
    public static function prepare(): void {
        Cipher::key('key');Cipher::key('index_key');
        $pdo=DB::connection()->getPdo();$sqlite=DB::getDriverName()==='sqlite';
        // Check every target before the first DDL change. Never erase an existing database implicitly.
        foreach(Registry::tables() as $table=>$fields)if(Schema::hasTable($table)&&array_intersect(array_keys($fields),Schema::getColumnListing($table))){
            if($pdo->query('SELECT 1 FROM '.self::quote($table).' LIMIT 1')->fetchColumn()!==false)throw new RuntimeException('Fresh database required. Existing data was not modified.');
        }
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
            else {
                $metadata=$pdo->query('SHOW COLUMNS FROM '.self::quote($table))->fetchAll(\PDO::FETCH_ASSOC);$nullable=array_column($metadata,'Null','Field');
                Schema::table($table,function(Blueprint $t)use($fields,$nullable){foreach($fields as $column=>$type)$t->longText($column)->nullable(($nullable[$column]??'YES')==='YES')->default(null)->change();});
            }
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
        $temp='privacy_schema_'.substr(hash('sha256',$table),0,16);
        $pdo->exec('CREATE TABLE '.self::quote($temp).' ('.implode(',',$parts).')');
        $pdo->exec('DROP TABLE '.$q);$pdo->exec('ALTER TABLE '.self::quote($temp).' RENAME TO '.$q);
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
}
