<?php
namespace App\Privacy;
class PrivateSessionHandler extends \Illuminate\Session\FileSessionHandler
{
    public function read($sessionId): string {
        $stored=parent::read($sessionId);return $stored===''?'':Cipher::unseal('sessions',$stored);
    }
    public function write($sessionId,$data): bool {return parent::write($sessionId,Cipher::seal('sessions',$data));}
}
