<?php

namespace Fpdo;

use PDO;
use PDOStatement;
use Vimeo\MysqlEngine\Server;

class FakePdoLogger{

    protected static $logs = [];


    public static function log(PDOStatement $stmt,string $query,array $params = [],int $affectedRows,float $start,float $end)
    {

        $connection = $stmt->getConnection();
        $database   = $connection->databaseName;

        $duration = $end-$start;

        $log[] = "[".date('Y-m-d H:i:s')."]";
        $log[] = "Database:      {$database}";
        $log[] = "Start:         {$start}";
        $log[] = "Duration:      {$duration}";
        $log[] = "Query:         ".str_replace(["\r","\n","\r\n"],' ',$query);
        $log[] = "Params:        ".json_encode($params);
        $log[] = "Affected Rows: {$affectedRows}";

        static::$logs[] = implode("\n",$log);
    }

}