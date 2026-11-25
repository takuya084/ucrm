<?php
namespace App\Privacy;
class EncryptedLogFormatter implements \Monolog\Formatter\FormatterInterface
{
    public function format($record): string {
        try {return Cipher::seal('logs',(new \Monolog\Formatter\JsonFormatter())->format($record))."\n";}
        catch(\Throwable){return "[private-log-unavailable]\n";}
    }
    public function formatBatch(array $records): string {return implode('',array_map([$this,'format'],$records));}
}
