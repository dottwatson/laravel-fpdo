<?php 

namespace Fpdo\Database;

use Fpdo\Exceptions\FpdoException;
use Illuminate\Database\MySqlConnection as BaseConnection;

class Connection extends BaseConnection{

    /**
     * The Fpdo
     *
     * @var \Fpdo\Php7\Fpdo|\Fpdo\Php8\Fpdo
     */
    protected $pdo;

    protected $tableSchemaFiles = [
        'columns' => [
            'TABLE_SCHEMA' => ['type'=>'text','length'=>64],
            'TABLE_NAME' => ['type'=>'text','length'=>64],
            'COLUMN_NAME' => ['type'=>'text','length'=>64],
            'ORDINAL_POSITION' => ['type'=>'integer','length'=>21],
            'COLUMN_DEFAULT' => ['type'=>'text'],
            'IS_NULLABLE' => ['type'=>'text','length'=>3],
            'DATA_TYPE' => ['type'=>'text','length'=>64],
            'CHARACTER_MAXIMUM_LENGTH' => [['type'=>'integer','length'=>21]],
            'CHARACTER_SET_NAME' => ['type'=>'text','length'=>32],
            'COLLATION_NAME' => ['type'=>'text','length'=>32],
            'COLUMN_TYPE' => ['type'=>'text'],
            'EXTRA' => ['type'=>'text','length'=>30],
        ]
    ];


    /**
     * Create a new database connection instance.
     *
     * @param  \PDO|\Closure  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array  $config
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        $this->pdo = $pdo;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();


        $this->initTableSchema();
    }

    
    protected function initTableSchema()
    {
        $key ="database.connections.{$this->database}"; 
        if(config("{$key}.use_information_schema")){
            $basePath = config("{$key}.use_information_schema",storage_path("fpdo"));
            if(!is_dir("{$basePath}/information_schema")){
                mkdir("{$basePath}/information_schema",0775,true);
                
                if(!is_file("{$basePath}/information_schema")){}
            }

            if(!is_dir("{$basePath}/{$this->database}")){
                mkdir("{$basePath}/{$this->database}",0775,true);
            }

            if(!config('database.connections.information_schema')){
                config(['database.connections.information_schema' =>[
                        'driver' => 'fpdo',
                        'charset' => 'utf8mb4',
                        'prefix' => '',
                        'prefix_indexes' => true,
                        'tables' => [
                            'columns' => [
                                'input' => [
                                    'type'      => 'csv',
                                    'source'    => "{$basePath}/{$this->database}/columns.csv",
                                    'options'   => [
                                        'use_header' => true,
                                    ],
                                ],
                                'output' => [
                                    'type'      => 'csv',
                                    'source'    => "{$basePath}/{$this->database}/columns.csv",
                                    'options'   => [
                                        'use_header' => true,
                                    ],
                                ],
                                'schema' => [
                                    
                                ]
                            ]
                        ]
                    ]
                ]);
            }
            //carico information schema
            //carico le tabelle
            // @todo: creare il reader e writer in crypt/descrypt di laravel
        }

    }


    public function createDatabase(string $database,array $config = [])
    {
        if(config("database.connections.{$database}")){
            throw new FpdoException("The database {$database} already exists");
        }
        
        $tables = $config['tables'] ?? [];
        unset($config['tables']);

        $config = array_replace_recursive([
            'driver'            => 'fpdo',
            'charset'           => config('fpdo.defautl_charset'),
            'prefix'            => '',
            'prefix_indexes'    => true,
            'tables'            => []
        ],$config);

        config(["database.connections.{$database}" => $config]);

        if($tables){
            foreach($tables as $tableName=>$tableConfig){
                $this->createTable($database,$tableName,$tableConfig);
            }
        }

    }
    
    /**
     * cretae a database, or a database with a table and a set of data
     *
     * @param string $database
     * @param string|null $table
     * @param array $data
     * @return void
     */
    public function createTable(string $database, string $table = null,array $tableConfiguration = [])
    {
        $alreadyExists = config("database.connections.{$database}.tables.{$table}",false);

        if($alreadyExists !== false){
            throw new FpdoException("Table {$table} is already defined in {$database}");
        }

        $tables = config("database.connections.{$database}.tables",[]);
        $tables[$table] = $tableConfiguration;
        config(["database.connections.{$database}.tables"=>$tables]);

        $this->pdo->getServer()->getTableDefinition($database,$table);
    }


    /**
     * returns current pdo
     *
     * @return \Fpdo\Php7\Fpdo|\Fpdo\Php8\Fpdo
     */
    public function getPdo()
    {
        return $this->pdo;
    }


    /**
     * Dump the database or a pair [tabelName=>dumpcConfig] list o f tables
     * Format of dumpConfig:
     * [
     *      'schema' => true|false Dump the table creation
     *      'not_exists' => true|false, If true and schema, the creation is with IF NOT EXISTS clause
     *      'force'      => true|false, If true and schema, the DROP TABLE is added
     *      'data'       => true|false, If true the INSERT statements will be added  
     *      'truncate'   => true|false  If true, the TRUNCATE TABLE is added
     * ]
     * @param null|string|array $tables
     * @return string
     */
    public function dump($tables = null)
    {

        if(!is_array($tables) && !is_null($tables)){
            throw new FpdoException("Specify an array like [table=>[]] for export");
        }

        $start = now();
        $dump  = "-- -----------------------------------------------------------------------------------------------------\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("Server ".getHostByName(getHostName()),40)."----\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("Engine Fpdo",40)."----\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("Date ".date('Y-m-d H:i:s'),40)."----\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("Database {$this->database}",40)."----\n";
        $dump .= "-- -----------------------------------------------------------------------------------------------------\n\n\n\n\n\n";

        if($tables === null){
            $tables = array_keys(config("database.connections.{$this->database}.tables",[]));
            foreach($tables as $table){
                $dump.=$this->dumpTable($table,[]);
            }
        }
        else{
            foreach($tables as $table=>$tableConfig){
                $dump.=$this->dumpTable($table,$tableConfig);
            }
        }

        $stop = now();
        
        $dump .= "-- -----------------------------------------------------------------------------------------------------\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("Duration ".($stop->diff($start)->format('%H:%I:%S')),40)."----\n";
        $dump .= "-- -------------------------------------------------------- ".str_pad("End at ".$stop->format('Y-m-d H:i:s'),40)."----\n";
        $dump .= "-- -----------------------------------------------------------------------------------------------------\n\n\n\n\n\n";
        return $dump;
    }

    
    protected function dumpTable($table,array $dumpConfig = [])
    {
        $server = $this->pdo->getServer();
        
        $config = array_replace_recursive([
            'schema'        => true,
            'not_exists'    => true,
            'force'         => true,
            'data'          => true,
            'truncate'      => true
        ],$dumpConfig);

        $tableDump = ["\n\n-- -------------------------------------------------------- Dump of table {$table}"];

        //because the tables are lazy loaded,emulate a query to make the table present
        $fakeQuery = $this->table($table)->where($this->raw(1),'!=',1)->get();

        if($config['schema']){
            if($config['force']){
                $tableDump[] = "DROP TABLE IF EXISTS {$table}";
            }
            
            $schemaQuery = $server->tablesCreationQuery[$table];

            if($config['not_exists']) {
                $schemaQuery = preg_replace("~CREATE TABLE~i","CREATE TABLE IF NOT EXISTS",$schemaQuery,1);
            }

            $tableDump[] = $schemaQuery;
        }

        if($config['truncate']){
            $tableDump[] = "TRUNCATE TABLE {$table}";
        }

        if($config['data']){
            $insert = [];
            $columns = $server->getTableDefinition($this->database,$table)->columns;

            $sqlColumns = [];

            collect($columns)->each(function($item,$key) use (&$sqlColumns){
                $sqlColumns[] = "`{$key}`";
            });

            $insert = ["INSERT INTO {$table}(".implode(', ',$sqlColumns).")","VALUES"];
            $values = [];


            foreach( $this->table($table)->select('*')->cursor() as $row ){
                $dataRow = get_object_vars($row);
                foreach($dataRow as $k=>$value){
                    if(is_string($value)){
                        $value = str_replace("'", "''", $value);
                        $value = $this->pdo->quote($value);
                    }
                    $dataRow[$k] = $value;
                }

                $values[] = '('.implode(", ",$dataRow).')';
            }

            if($values){
                $insert[] = implode(",\n",$values);
                $tableDump[] = implode("\n",$insert);
            }

        }

        $tableDump[] = "\n-- -------------------------------------------------------- End of dump table {$table}\n\n\n\n";

        return implode(";\n\n",$tableDump);
    }


    public function save($tables = null)
    {
        $pdo = $this->pdo;
        $server = $pdo->getServer();
        $server->saveOutputTable($pdo,$tables);       
        
        // $this->pdo->getServer()->saveOutputTable($this->pdo,)        
    }

}
