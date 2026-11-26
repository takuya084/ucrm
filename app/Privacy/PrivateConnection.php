<?php
namespace App\Privacy;
trait PrivateConnection
{
    public function query() {return new QueryBuilder($this,$this->getQueryGrammar(),$this->getPostProcessor());}
}
