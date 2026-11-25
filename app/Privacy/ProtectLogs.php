<?php
namespace App\Privacy;
class ProtectLogs
{
    public function __invoke($logger): void {
        foreach($logger->getHandlers() as $handler){
            if($handler instanceof \Monolog\Handler\StreamHandler||$handler instanceof \Monolog\Handler\ErrorLogHandler)$handler->setFormatter(new EncryptedLogFormatter());
            elseif(!$handler instanceof \Monolog\Handler\NullHandler)throw new \RuntimeException('A logging handler without private formatting is not allowed.');
        }
    }
}
