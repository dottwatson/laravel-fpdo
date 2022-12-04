<?php
namespace Fpdo;

use Vimeo\MysqlEngine\FakePdoStatementTrait;
use Fpdo\FpdoLog;

trait FpdoStatementTrait
{
    use FakePdoStatementTrait{
        FakePdoStatementTrait::universalFetchAll as parentUniversalFetchAll;
        FakePdoStatementTrait::universalExecute as parentUniversalExecute;

    }


    public function universalExecute(?array $params = null)
    {
        $start = microtime(true);
        $result = $this->parentUniversalExecute($params);

        $duration = microtime(true) - $start;
        if(config('fpdo.log.enabled') && config('fpdo.log.query.enabled') && $duration >= config('fpdo.log.query.max_execution_time')){
            $addSlashes = str_replace(['?', '%'], ["'?'", '%%'],$this->sql);
            $fullSql =  vsprintf(str_replace('?', '%s', $addSlashes), $this->boundValues);
            FpdoLog::query($duration,$fullSql);
        }

        return $result;
    }

    /**
     * @param  int $fetch_style
     * @param  mixed      $args
     */
    public function universalFetchAll(int $fetch_style = -123, ...$args) : array
    {
        if ($fetch_style === -123) {
            $fetch_style = $this->fetchMode;
            $fetch_argument = $this->fetchArgument;
            $ctor_args = $this->fetchConstructorArgs;
        } else {
            $fetch_argument = $args[0] ?? null;
            $ctor_args = $args[1] ?? [];
        }

        if ($fetch_style === \PDO::FETCH_ASSOC) {
            return array_map(
                function ($row) {
                    if ($this->conn->shouldStringifyResult()) {
                        $row = self::stringify($row);
                    }

                    if ($this->conn->shouldLowercaseResultKeys()) {
                        $row = self::lowercaseKeys($row);
                    }

                    return $row;
                },
                $this->result ?: []
            );
        }

        if ($fetch_style === \PDO::FETCH_OBJ) {
            return array_map(
                function ($row) {
                    if ($this->conn->shouldStringifyResult()) {
                        $row = self::stringify($row);
                    }

                    if ($this->conn->shouldLowercaseResultKeys()) {
                        $row = self::lowercaseKeys($row);
                    }

                    return (object)$row;
                },
                $this->result ?: []
            );
        }

        if ($fetch_style === \PDO::FETCH_NUM) {
            return array_map(
                function ($row) {
                    if ($this->conn->shouldStringifyResult()) {
                        $row = self::stringify($row);
                    }

                    return \array_values($row);
                },
                $this->result ?: []
            );
        }

        if ($fetch_style === \PDO::FETCH_BOTH) {
            return array_map(
                function ($row) {
                    if ($this->conn->shouldStringifyResult()) {
                        $row = self::stringify($row);
                    }

                    if ($this->conn->shouldLowercaseResultKeys()) {
                        $row = self::lowercaseKeys($row);
                    }

                    return array_merge($row, \array_values($row));
                },
                $this->result ?: []
            );
        }

        if ($fetch_style === \PDO::FETCH_COLUMN && $fetch_argument !== null) {
            return \array_column(
                array_map(
                    function ($row) {
                        if ($this->conn->shouldStringifyResult()) {
                            $row = self::stringify($row);
                        }

                        return \array_values($row);
                    },
                    $this->result ?: []
                ),
                $fetch_argument
            );
        }

        if ($fetch_style === \PDO::FETCH_CLASS) {
            if (!$this->result) {
                return [];
            }

            return array_map(
                function ($row) use ($fetch_argument, $ctor_args) {
                    if ($this->conn->shouldStringifyResult()) {
                        $row = self::stringify($row);
                    }

                    if ($this->conn->shouldLowercaseResultKeys()) {
                        $row = self::lowercaseKeys($row);
                    }

                    return self::convertRowToObject($row, $fetch_argument, $ctor_args);
                },
                $this->result
            );
        }

        throw new \Exception('Fetch style not implemented');
    }


    public function getConnection()
    {
        return $this->conn;
    }

}
