<?php
namespace Fpdo;
use Vimeo\MysqlEngine\Server as FakeServer;
use Fpdo\TableData;
use Illuminate\Support\Arr;
use Vimeo\MysqlEngine\DataIntegrity;


class Server extends FakeServer{

    protected $pdo;
    
    /**
     * The create table queries
     *
     * @var array
     */
    public $tablesCreationQuery = [];

    /**
     * get o create a new server
     *
     * @param string $name
     * @return static
     */
    public static function getOrCreate(string $name) : self
    {
        $server = static::$instances[$name] ?? null;
        if ($server === null) {
            $server = new static($name);
            static::$instances[$name] = $server;
        }

        return $server;
    }

    /**
     * Set the pdo
     *
     * @param [type] $pdo
     * @return void
     */
    public function setPdo($pdo)
    {
        $this->pdo = $pdo;
    }


    /**
     * get table definiton
     *
     * @param string $database
     * @param string $table
     * @return \Vimeo\MysqlEngine\Schema\TableDefinition|null
     */
    public function getTableDefinition(string $database, string $table) : ?\Vimeo\MysqlEngine\Schema\TableDefinition
    {
        if(!isset($this->tableDefinitions[$database][$table])){
            $tableDefinition = config("database.connections.{$database}.tables.{$table}");
            $tableDefinition['charset'] = $tableDefinition['charset'] ?? config('fpdo.default_charset');

            //this allows us to 'lazyload' tables where needed. 
            //This is a benefit, because does not consume memory and time, preserving a fast and agile execution  
            if($tableDefinition){

                //detect the correct parser for data
                $parser = TableData::resolveReader($database,$table);

                //try to make the create query to define the table and fill it with its resource info
                $pdoCls             = 'Fpdo\\Php'.PHP_MAJOR_VERSION.'\\Fpdo';
                
                if(!is_array($parser->getData())){
                    throw new \Exception("FPDO: Unable to read {$table} for database {$database}");
                }
                
                $queryTableCreate = TableData::getSqlCreateTable($parser,$tableDefinition['charset'],$this->pdo);

                $this->tablesCreationQuery[$table] = $queryTableCreate;
                $this->pdo->exec($queryTableCreate);
                $this->saveTable($database,$table,$parser->getData());
            }
        }

        return $this->tableDefinitions[$database][$table] ?? null;
    }


    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getTable(string $database, string $table) : ?array
    {
        if(!isset($this->tableDefinitions[$database][$table])){
            $tableDefinition            = config("database.connections.{$database}.tables.{$table}");
            $tableDefinition['charset'] = $tableDefinition['charset'] ?? config('fpdo.default_charset');

            //this allows us to 'lazyload' tables where needed. 
            //This is a benefit, because does not consume memory and time, preserving a fast and agile execution  
            if($tableDefinition){
                //detect the correct parser for data
                $parser = TableData::resolveReader($database,$table);

                //try to make the create query to define the table and fill it with its resource info
                $pdoCls             = 'Fpdo\\Php'.PHP_MAJOR_VERSION.'\\Fpdo';

                if(!is_array($parser->getData())){
                    throw new \Exception("Unable to load the FPDO resource {$table} into database {$database}");
                }
                
                $queryTableCreate = TableData::getSqlCreateTable($parser,$tableDefinition['charset'],$this->pdo);
                $this->tablesCreationQuery[$table] = $queryTableCreate;

                $this->pdo->exec($queryTableCreate);
                $this->saveTable($database,$table,$parser->getData());
            }
        }

        return $this->databases[$database][$table]->table ?? null; 
    }

    /**
     * save teh table in the fake database
     *
     * @param string $database_name
     * @param string $table_name
     * @param array $rows
     * @return void
     */
    public function saveTable(string $database_name, string $table_name, array $rows) : void
    {
        if (!isset($this->databases[$database_name][$table_name])) {
            $this->databases[$database_name][$table_name] = new TableData($this,$database_name,$table_name);
        }

        $schema = $this->tableDefinitions[$database_name][$table_name];


        $pdo = $this->pdo;

        //adapt data to database tabel columns definition
        $rows = array_map(function($row) use ($schema,$pdo){
            foreach($row as $name=>$value){
                if(isset($schema->columns[$name]) && is_a($schema->columns[$name], \Vimeo\MysqlEngine\Schema\Column\Json::class)){
                    $row[$name] = json_encode($value);
                }
            }

            return DataIntegrity::coerceToSchema($pdo,$row,$schema);
        },$rows);

        $this->databases[$database_name][$table_name]->table = $rows;
    }
    
    /**
     * reset the table as its original state before any insert/update/delete action
     *
     * @param string $database_name
     * @param string $table_name
     * @return void
     */
    public function resetTable(string $database_name, string $table_name) : void
    {
        static::$snapshot_names = [];
        $this->snapshots = [];
        $this->databases[$database_name][$table_name] = new TableData($this,$database_name,$table_name);
        $this->databases[$database_name][$table_name]->was_truncated = true;
    }

    public function getNextAutoIncrementValue(string $database_name,string $table_name,string $column_name) : int 
    {
        $table_definition = $this->getTableDefinition($database_name, $table_name);

        $table = $this->databases[$database_name][$table_name] ?? null;

        if (!$table_definition) {
            throw new \UnexpectedValueException('table doesn’t exist');
        }

        if (!$table) {
            $table = $this->databases[$database_name][$table_name] = new TableData($this,$database_name,$table_name);
        }

        if (!isset($table->autoIncrementCursors[$column_name])) {
            if (isset($table_definition->autoIncrementOffsets[$column_name]) && !$table->was_truncated) {
                $table->autoIncrementCursors[$column_name] = $table_definition->autoIncrementOffsets[$column_name] - 1;
            } else {
                $table->autoIncrementCursors[$column_name] = 0;
            }
        }

        return $table->autoIncrementCursors[$column_name] + 1;
    }

    /**
     * set this auto increent value 
     *
     * @param string $database_name
     * @param string $table_name
     * @param string $column_name
     * @param integer $value
     * @return integer
     */
    public function addAutoIncrementMinValue(string $database_name,string $table_name,string $column_name,int $value): int
    {
        $table_definition = $this->getTableDefinition($database_name, $table_name);
        $table = $this->databases[$database_name][$table_name] ?? null;

        if (!$table_definition) {
            throw new \UnexpectedValueException('table doesn’t exist');
        }

        if (!$table) {
            $table = $this->databases[$database_name][$table_name] = new TableData($this,$database_name,$table_name);
        }

        return $table->autoIncrementCursors[$column_name] = max(
            $table->autoIncrementCursors[$column_name] ?? 0,
            $value
        );
    }

}





