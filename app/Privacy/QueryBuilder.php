<?php
namespace App\Privacy;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use RuntimeException;

class QueryBuilder extends Builder
{
    private function privateColumn($column): ?array {
        if (!is_string($column)||!is_string($this->from)) return null;
        $base=Registry::table($this->from);$name=$column;$table=$base;$qualifier=$base;
        if (str_contains($column,'.')) {
            [$prefix,$name]=explode('.',$column,2);$qualifier=$prefix;
            $sources=[$this->from];foreach($this->joins??[] as $join) if(is_string($join->table))$sources[]=$join->table;
            foreach($sources as $source) {
                $parts=preg_split('/\s+(?:as\s+)?/i',trim($source));
                if ($prefix===$parts[0]||$prefix===($parts[1]??null)) {$table=$parts[0];break;}
            }
            if (isset(Registry::tables()[$prefix])) $table=$prefix;
        }
        return Registry::protected($table,$name)?[$table,$name,$qualifier]:null;
    }
    private function rewriteIndex(string $column,array $info): string {
        return str_contains($column,'.')?$info[2].'.'.Registry::indexColumn($info[1]):Registry::indexColumn($info[1]);
    }
    protected function runSelect() {
        $rows=parent::runSelect();$base=is_string($this->from)?Registry::table($this->from):'';$aliases=[];
        foreach($this->columns??[] as $column) if (is_string($column)&&preg_match('/^([a-z_][a-z0-9_.]*)\s+as\s+([a-z_][a-z0-9_]*)$/i',$column,$m)) $aliases[$m[2]]=$this->privateColumn($m[1]);
        foreach($rows as $row) foreach((array)$row as $field=>$value) {
            if (str_starts_with($field,'pii_i_')) {if(is_object($row))unset($row->$field);continue;}
            $context=isset($aliases[$field])?$aliases[$field][0].'.'.$aliases[$field][1]:(Registry::protected($base,$field)?$base.'.'.$field:null);
            if ($value!==null && ($context!==null||is_string($value)&&str_starts_with($value,Cipher::PREFIX))) $row->$field=$context!==null?Cipher::decrypt($context,$value):Cipher::decryptProjection($value);
        }
        return $rows;
    }
    private function valuesForWrite(array $values): array {
        $table=is_string($this->from)?Registry::table($this->from):'';
        if (!$values) return $values;
        if (!is_array(reset($values))) return Registry::encode($table,$values,true);
        return array_map(fn($row)=>Registry::encode($table,$row,true),$values);
    }
    public function insert(array $values) {return parent::insert($this->valuesForWrite($values));}
    public function insertOrIgnore(array $values) {return parent::insertOrIgnore($this->valuesForWrite($values));}
    public function insertGetId(array $values,$sequence=null) {return parent::insertGetId($this->valuesForWrite($values),$sequence);}
    public function update(array $values) {return parent::update(Registry::encode(Registry::table($this->from),$values));}
    public function upsert(array $values,$uniqueBy,$update=null) {
        $table=Registry::table($this->from);$uniqueBy=array_map(fn($c)=>Registry::protected($table,$c)?Registry::indexColumn($c):$c,(array)$uniqueBy);
        if($update!==null)foreach($update as $column)if(Registry::protected($table,$column))$update[]=Registry::indexColumn($column);
        return parent::upsert($this->valuesForWrite($values),$uniqueBy,$update);
    }
    public function where($column,$operator=null,$value=null,$boolean='and') {
        if (func_num_args()===2) {$value=$operator;$operator='=';}
        if ($value instanceof \DateTimeInterface) $value=$value->format('Y-m-d H:i:s');
        if (($info=$this->privateColumn($column)) && !($value instanceof Expression) && $value!==null && !is_object($value)) {
            $op=strtolower((string)$operator);
            if (in_array($op,['=','!=','<>'],true)) return parent::where($this->rewriteIndex($column,$info),$operator,Cipher::index($info[0].'.'.$info[1],$value),$boolean);
            return $this->privateMatch($info,fn($v)=>$this->compare($v,$op,$value),$boolean);
        }
        return parent::where($column,$operator,$value,$boolean);
    }
    public function whereIn($column,$values,$boolean='and',$not=false) {
        if (($info=$this->privateColumn($column)) && !($values instanceof \Closure) && !($values instanceof Builder) && !($values instanceof \Illuminate\Database\Eloquent\Builder)) {
            $values=$values instanceof \Illuminate\Contracts\Support\Arrayable?$values->toArray():(array)$values;
            return parent::whereIn($this->rewriteIndex($column,$info),array_map(fn($v)=>Cipher::index($info[0].'.'.$info[1],$v),$values),$boolean,$not);
        }
        return parent::whereIn($column,$values,$boolean,$not);
    }
    public function whereBetween($column,iterable $values,$boolean='and',$not=false) {
        if ($info=$this->privateColumn($column)) {
            $v=collect($values)->values()->all();
            return $this->privateMatch($info,fn($p)=>(($this->compare($p,'>=',$v[0])&&$this->compare($p,'<=',$v[1]))!==$not),$boolean);
        }
        return parent::whereBetween($column,$values,$boolean,$not);
    }
    protected function addDateBasedWhere($type,$column,$operator,$value,$boolean='and') {
        if ($info=$this->privateColumn($column)) return $this->privateMatch($info,function($p)use($type,$operator,$value){
            if($p===null)return false;$p=(string)$p;
            $selected=match(strtolower($type)){'date'=>substr($p,0,10),'year'=>substr($p,0,4),'month'=>substr($p,5,2),'day'=>substr($p,8,2),'time'=>strlen($p)>10?substr($p,11,8):$p,default=>throw new RuntimeException('Unsupported private date operation')};
            return $this->compare($selected,$operator,$value);
        },$boolean);
        return parent::addDateBasedWhere($type,$column,$operator,$value,$boolean);
    }
    private function compare($left,string $op,$right): bool {
        if ($right instanceof \DateTimeInterface) $right=$right->format('Y-m-d H:i:s');
        if ($left===null)return false;
        if (in_array($op,['like','not like'],true)) {
            $pattern='';$escaped=false;foreach(preg_split('//u',(string)$right,-1,PREG_SPLIT_NO_EMPTY) as $ch) {
                if($escaped){$pattern.=preg_quote($ch,'~');$escaped=false;}elseif($ch==='\\')$escaped=true;elseif($ch==='%')$pattern.='.*';elseif($ch==='_')$pattern.='.';else$pattern.=preg_quote($ch,'~');
            }
            return (preg_match('~^'.$pattern.'$~isu',(string)$left)===1)===($op==='like');
        }
        return match($op){'='=>$left==$right,'!=','<>'=>$left!=$right,'>'=>$left>$right,'>='=>$left>=$right,'<'=>$left<$right,'<='=>$left<=$right,default=>throw new RuntimeException('Unsupported comparison on encrypted field: '.$op)};
    }
    private function privateRows(array $info): array {
        [$table,$column]=$info;$pdo=$this->connection->getPdo();$grammar=$this->grammar;
        $key=$this->privatePrimaryKey($table);$query=$pdo->query('SELECT '.$grammar->wrap($key).','.$grammar->wrap($column).' FROM '.$grammar->wrapTable($table));$rows=[];
        while($r=$query->fetch(\PDO::FETCH_ASSOC))$rows[]=['id'=>$r[$key],'value'=>Cipher::decrypt($table.'.'.$column,$r[$column])];
        return $rows;
    }
    private function privatePrimaryKey(string $table): string {
        return match($table){'cache'=>'key',default=>'id'};
    }
    private function privateMatch(array $info,callable $predicate,string $boolean) {
        $ids=[];foreach($this->privateRows($info) as $row)if($predicate($row['value']))$ids[]=$row['id'];
        $column=$info[2].'.'.$this->privatePrimaryKey($info[0]);
        if ($ids && count(array_filter($ids,fn($id)=>is_numeric($id)))===count($ids))return parent::whereIntegerInRaw($column,$ids,$boolean);
        return parent::whereIn($column,$ids,$boolean);
    }
    public function orderBy($column,$direction='asc') {
        if ($info=$this->privateColumn($column)) {
            $direction=strtolower($direction);if(!in_array($direction,['asc','desc'],true))throw new RuntimeException('Invalid ordering');
            $rows=$this->privateRows($info);usort($rows,fn($a,$b)=>is_numeric($a['value'])&&is_numeric($b['value'])?($a['value']<=>$b['value']):strnatcasecmp((string)$a['value'],(string)$b['value']));
            if(!$rows)return $this;
            $key=$this->grammar->wrap($info[2].'.'.$this->privatePrimaryKey($info[0]));$sql='CASE '.$key;$bindings=[];
            foreach($rows as $rank=>$row){$sql.=' WHEN ? THEN '.$rank;$bindings[]=$row['id'];}
            return parent::orderByRaw($sql.' ELSE '.count($rows).' END '.$direction,$bindings);
        }
        return parent::orderBy($column,$direction);
    }
    public function aggregate($function,$columns=['*']) {
        if (count($columns)===1 && ($info=$this->privateColumn($columns[0])) && in_array(strtolower($function),['sum','avg','min','max','count'],true)) {
            $query=$this->cloneWithout(['orders','limit','offset','aggregate','columns'])->cloneWithoutBindings(['order']);$values=$query->pluck($columns[0])->filter(fn($v)=>$v!==null);
            return match(strtolower($function)){'sum'=>$values->reduce(fn($a,$v)=>bcadd((string)$a,(string)$v,8),'0'),'avg'=>$values->count()?bcdiv($values->reduce(fn($a,$v)=>bcadd((string)$a,(string)$v,8),'0'),(string)$values->count(),8):null,'min'=>$values->min(),'max'=>$values->max(),'count'=>$this->distinct?$values->unique()->count():$values->count()};
        }
        return parent::aggregate($function,$columns);
    }
}
