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
        foreach(Registry::tables() as $table=>$fields) {
            if(!Schema::hasTable($table))continue;
            $columns=Schema::getColumnListing($table);$fields=array_intersect_key($fields,array_flip($columns));
            if(!$fields)continue;
            $indexes=self::indexes($table);$changed=[];
            foreach($indexes as $index) {
                if(!array_intersect($index['columns'],array_keys($fields)))continue;
                Schema::table($table,function(Blueprint $t)use($index){
                    if($index['primary'])$t->dropPrimary();elseif($index['unique'])$t->dropUnique($index['name']);else$t->dropIndex($index['name']);
                });$changed[]=$index;
            }
            Schema::table($table,function(Blueprint $t)use($fields,$columns){foreach($fields as $column=>$type)if(!in_array(Registry::indexColumn($column),$columns,true))$t->char(Registry::indexColumn($column),64)->nullable();});
            Schema::table($table,function(Blueprint $t)use($fields){foreach($fields as $column=>$type)$t->text($column)->nullable()->default(null)->change();});
            self::encryptTable($table,array_keys($fields));
            foreach($changed as $index) {
                $new=array_map(fn($c)=>isset($fields[$c])?Registry::indexColumn($c):$c,$index['columns']);
                Schema::table($table,function(Blueprint $t)use($index,$new){if($index['primary'])$t->primary($new);elseif($index['unique'])$t->unique($new,$index['name']);else$t->index($new,$index['name']);});
            }
        }
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
    public static function encryptTable(string $table,array $columns,bool $dry=false): array {
        $pdo=DB::connection()->getPdo();$sqlite=DB::getDriverName()==='sqlite';$names=Schema::getColumnListing($table);
        $key=in_array('id',$names,true)?'id':(in_array('key',$names,true)?'key':'email');
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
                    $updates[$column]=Cipher::encrypt($table.'.'.$column,$plain);$counts['encrypted']++;
                }
                $updates[Registry::indexColumn($column)]=Cipher::index($table.'.'.$column,$plain);
            }
            if($dry||!$updates)continue;
            $where=$sqlite?'rowid':$key;$identity=$sqlite?$row['privacy_rowid']:$row[$key];
            $sql='UPDATE '.self::quote($table).' SET '.implode(',',array_map(fn($c)=>self::quote($c).'=?',array_keys($updates))).' WHERE '.self::quote($where).'=?';
            $statement=$pdo->prepare($sql);$statement->execute([...array_values($updates),$identity]);
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
            $rows=DB::connection()->getPdo()->query('SELECT '.implode(',',array_map([self::class,'quote'],$columns)).' FROM '.self::quote($table));
            while($row=$rows->fetch(\PDO::FETCH_ASSOC))foreach($row as $column=>$value)if($value!==null){
                if(is_string($value)&&str_starts_with($value,Cipher::PREFIX)){Cipher::decrypt($table.'.'.$column,$value);$result['verified']++;}else$result['plaintext']++;
            }
        }
        return $result;
    }
}
