<?php

namespace Fpdo;

class FpdoLog{

    public static function query(float $duration,string $message)
    {
        static::log("query taken {$duration} : {$message}");
    }
    
    public static function reader(float $duration,string $message)
    {
        static::log("read {$message} taken $duration to be readed");
    }

    public static function writer(float $duration,string $message)
    {
        static::log("write {$message} taken $duration to be wrote");
    }

    protected static function log(string $message)
    {
        $line = '['.date('Y-m-d H:i:s.u').'] local.INFO '.$message."\n";
        file_put_contents(config('fpdo.log.file'),$line,FILE_APPEND|LOCK_EX);
    }

}