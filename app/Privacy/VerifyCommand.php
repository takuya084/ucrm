<?php
namespace App\Privacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};
use RuntimeException;
class VerifyCommand extends Command
{
    protected $signature='privacy:verify';
    protected $description='Verify personal database values and lookup integrity without printing their contents';
    public function handle(): int {
        Cipher::key();$verified=0;$plaintext=0;
        foreach(Registry::tables() as $table=>$fields){
            if(!Schema::hasTable($table))continue;
            $columns=array_values(array_intersect(array_keys($fields),Schema::getColumnListing($table)));if(!$columns)continue;
            $selected=array_merge($columns,array_map([Registry::class,'indexColumn'],$columns));
            $query=DB::connection()->getPdo()->query('SELECT '.implode(',',array_map([SchemaSetup::class,'quote'],$selected)).' FROM '.SchemaSetup::quote($table));
            while($row=$query->fetch(\PDO::FETCH_ASSOC))foreach($columns as $column){
                $value=$row[$column];$lookup=$row[Registry::indexColumn($column)];
                if($value===null){if($lookup!==null)throw new RuntimeException('Private lookup verification failed.');continue;}
                if(!is_string($value)||!str_starts_with($value,Cipher::PREFIX)){$plaintext++;continue;}
                $plain=Cipher::decrypt($table.'.'.$column,$value);
                if(!hash_equals(Cipher::index($table.'.'.$column,$plain),(string)$lookup))throw new RuntimeException('Private lookup verification failed.');$verified++;
            }
        }
        $this->line(json_encode(compact('verified','plaintext')));return $plaintext?self::FAILURE:self::SUCCESS;
    }
}
