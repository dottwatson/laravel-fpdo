<?php
namespace Fpdo;


use Vimeo\MysqlEngine\TableData as FakeTableData;
use Fpdo\Resource;

use Fpdo\Reader\JsonReader;
use Fpdo\Reader\CsvReader;
use Fpdo\Reader\XmlReader;
use Fpdo\Reader\ArrayReader;

use Fpdo\Writer\JsonWriter;
use Fpdo\Writer\CsvWriter;
use Fpdo\Writer\XmlWriter;
use Fpdo\Writer\ArrayWriter;


use Illuminate\Support\Arr;
use PDO;

class TableData extends FakeTableData{

    const CSV_FORMAT       = 'csv';
    const JSON_FORMAT      = 'json';
    const ARRAY_FORMAT     = 'array';
    const XML_FORMAT       = 'xml';

    /**
     * The server
     *
     * @var Fpdo\Server|null
     */
    protected $server;

    /**
     * The database name
     *
     * @var string
     */
    protected $databaseName;
    
    /**
     * The current table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * The original table configuration 
     *
     * @var array
     */
    protected $tableConfig = [];


    /**
     * crate a table object
     *
     * @param Server $server
     * @param string $databaseName
     * @param string $tableName
     */
    public function __construct(Server $server,string $databaseName,string $tableName)
    {
        $this->server       = $server;
        $this->databaseName = $databaseName;
        $this->tableName    = $tableName;
        
        $this->tableConfig  = config("database.connections.{$this->databaseName}.tables.{$this->tableName}");

    }

    /**
     * get table configuration
     *
     * @return array
     */
    public function getTableConfig()
    {
        return $this->tableConfig;
    }

    /**
     * select appropriate parser from already available or binded
     *
     * @param string $database
     * @param string $table
     * @param int $mode 0 = read mode, 1 = write mode
     * @return \Fpdo\DataReader
     */
    public static function resolveReader(string $database, string $table)
    {
        $config = config("database.connections.{$database}.tables.{$table}.input");
        
        if($config === null){
            throw new \Exception("Fpdo: invalid database.connections.{$database}.tables.{$table} input data");
        }

        //try to read table info
        $dataType       = Arr::get($config,'type',null);
        $parser         = null;
        $declaredSchema = config("database.connections.{$database}.tables.{$table}.schema");

        switch($dataType){
            case self::JSON_FORMAT:
                $parser = new JsonReader($database,$table,$config,$declaredSchema);
            break;
            case self::CSV_FORMAT:
                $parser = new CsvReader($database,$table,$config,$declaredSchema);
            break;
            case self::XML_FORMAT:
                $parser = new XmlReader($database,$table,$config,$declaredSchema);
            break;
            case self::ARRAY_FORMAT:
                $parser = new ArrayReader($database,$table,$config,$declaredSchema);
            break;
            default:
                $binded = DataReader::getRegisteredParser();
                foreach($binded as $bindedParser=>$bindedClass){
                    if($dataType == $bindedParser){
                        $parser = new $bindedClass($database,$table,$config,$declaredSchema);
                        break;
                    }
                }
            break;
        }

        if($parser === null){
            throw new \Exception("Fpdo: `{$dataType}` is not a valid reader type for table {$table}");
        }

        return $parser;
    }

    public static function resolveWriter(string $database,string $table,array $data = [])
    {
        $config = config("database.connections.{$database}.tables.{$table}.output");
        
        if($config === null){
            throw new \Exception("Fpdo: invalid database.connections.{$database}.tables.{$table} output data");
        }

        //try to read table info
        $dataType       = Arr::get($config,'type',null);
        $parser = null;

        switch($dataType){
            case self::JSON_FORMAT:
                $parser = new JsonWriter($database,$table,$config,$data);
            break;
            case self::CSV_FORMAT:
                $parser = new CsvWriter($database,$table,$config,$data);
            break;
            case self::XML_FORMAT:
                $parser = new XmlWriter($database,$table,$config,$data);
            break;
            case self::ARRAY_FORMAT:
                $parser = new ArrayWriter($database,$table,$config,$data);
            break;
            default:
                $binded = DataWriter::getRegisteredParser();
                foreach($binded as $bindedParser=>$bindedClass){
                    if($dataType == $bindedParser){
                        $parser = new $bindedClass($database,$table,$config,$data);
                        break;
                    }
                }
            break;
        }

        if($parser === null){
            throw new \Exception("Fpdo: `{$dataType}` is not a valid writer type for table {$table}");
        }

        return $parser;
    }


    /**
     * build the table creation query
     *
     * @param DataReader $dataReader
     * @param string|null $tableCharset
     * @param PDO $pdoInstance
     * @return string
     */
    public static function getSqlCreateTable(DataReader $dataReader,string $tableCharset = null, PDO $pdoInstance)
    {
        $table          = $dataReader->getTable();
        $database       = $dataReader->getDatabase();
        $tableCharset   = $tableCharset ?? config('fpdo.default_charset','utf8mb4');

        $sqlColumns = [];
        foreach($dataReader->getColumns() as $column)
        {
            $customDefinition = $dataReader->config("schema.{$column}",[]); 
            $values = array_column($dataReader->getData(),$column);
            $columnDefinition = DataGuesser::makeSQlColumn($pdoInstance,$column,$values,$customDefinition);
            
            $sqlColumns[] = "{$columnDefinition['column']} {$columnDefinition['sql']}";//"{$column} {$columnDefinition}";
        }

        $sql = "CREATE TABLE {$table} (\n".implode(",\n",$sqlColumns)." \n) CHARACTER SET {$tableCharset}";
        return $sql;
    }
}