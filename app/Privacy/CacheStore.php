<?php
namespace App\Privacy;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Cache\LockProvider;
use RuntimeException;
class CacheStore implements Store,LockProvider
{
    public function __construct(private Store $backend){}
    private function key($key){return Cipher::index('cache.key',(string)$key);}
    private function envelope($key):?array {
        $raw=$this->backend->get($this->key($key));if($raw===null)return null;
        $data=unserialize(Cipher::unseal('cache',$raw),['allowed_classes'=>true]);
        if(!is_array($data)||!array_key_exists('value',$data)||!array_key_exists('expires',$data))throw new RuntimeException('Invalid private cache entry.');return $data;
    }
    public function get($key){return $this->envelope($key)['value']??null;}
    public function many(array $keys){$r=[];foreach($keys as $key)$r[$key]=$this->get($key);return $r;}
    public function put($key,$value,$seconds){return $this->backend->put($this->key($key),Cipher::seal('cache',serialize(['value'=>$value,'expires'=>time()+$seconds])),$seconds);}
    public function putMany(array $values,$seconds){foreach($values as $key=>$value)if(!$this->put($key,$value,$seconds))return false;return true;}
    public function add($key,$value,$seconds){return $this->lock('add:'.$key,10)->block(5,function()use($key,$value,$seconds){if($this->envelope($key)!==null)return false;return $this->put($key,$value,$seconds);});}
    public function forever($key,$value){return $this->backend->forever($this->key($key),Cipher::seal('cache',serialize(['value'=>$value,'expires'=>null])));}
    public function forget($key){return $this->backend->forget($this->key($key));}
    public function flush(){return $this->backend->flush();}
    public function getPrefix(){return $this->backend->getPrefix();}
    public function increment($key,$value=1){return $this->change($key,$value);}
    public function decrement($key,$value=1){return $this->change($key,-$value);}
    private function change($key,$amount){
        return $this->lock('counter:'.$key,10)->block(5,function()use($key,$amount){
            $data=$this->envelope($key);if($data===null)return false;$next=(int)$data['value']+$amount;
            if($data['expires']===null)$this->forever($key,$next);else$this->put($key,$next,max(1,$data['expires']-time()));return $next;
        });
    }
    public function lock($name,$seconds=0,$owner=null){
        if(!$this->backend instanceof LockProvider)throw new RuntimeException('Private cache requires an atomic lock provider.');
        return $this->backend->lock(Cipher::index('cache.lock',(string)$name),$seconds,$owner);
    }
    public function restoreLock($name,$owner){
        if(!$this->backend instanceof LockProvider)throw new RuntimeException('Private cache requires an atomic lock provider.');
        return $this->backend->restoreLock(Cipher::index('cache.lock',(string)$name),$owner);
    }
}
