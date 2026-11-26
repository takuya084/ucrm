<?php
namespace App\Privacy;
final class Registry
{
    public static function tables(): array {return config('privacy.tables',[]);}
    public static function fields(string $table): array {return self::tables()[$table]??[];}
    public static function protected(string $table,string $field): bool {return array_key_exists($field,self::fields($table));}
    public static function indexColumn(string $field): string {return 'pii_i_'.$field;}
    public static function table(string $from): string {return preg_split('/\s+(?:as\s+)?/i',trim($from))[0];}
    public static function normalize(string $table,string $column,mixed $value): mixed {
        if($value===null)return null;$type=self::fields($table)[$column]??'text';
        if($value instanceof \DateTimeInterface)$value=$value->format($type==='date'?'Y-m-d':'Y-m-d H:i:s');
        return match($type){'date'=>substr((string)$value,0,10),'integer','boolean'=>(int)$value,'float'=>(float)$value,'decimal'=>(string)$value,default=>$value};
    }
    public static function encode(string $table,array $values,bool $insert=false): array {
        if($insert)$values+=config('privacy.defaults.'.$table,[]);
        foreach (array_keys($values) as $column) if (str_starts_with($column,'pii_i_')) throw new \RuntimeException('Private lookup columns cannot be assigned directly.');
        foreach (self::fields($table) as $column=>$type) if (array_key_exists($column,$values)) {
            if ($values[$column] instanceof \Illuminate\Contracts\Database\Query\Expression) throw new \RuntimeException('A private column cannot be updated by a raw SQL expression.');
            $plain=self::normalize($table,$column,$values[$column]);$values[$column]=$plain===null?null:Cipher::encrypt($table.'.'.$column,$plain);
            $values[self::indexColumn($column)]=Cipher::index($table.'.'.$column,$plain);
        }
        return $values;
    }
}
