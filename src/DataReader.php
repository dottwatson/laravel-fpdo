<?php 
namespace Fpdo;

use Fpdo\DataManager;
use Fpdo\Exceptions\DataReaderException;
use Exception;
use Illuminate\Support\Arr;

abstract class DataReader{

   
    
    /**
     * the databse name
     *
     * @var string
     */
    protected $database;

    /**
     * the table name
     *
     * @var string
     */
    protected $table;


    /**
     * The source
     *
     * @var mixed
     */
    protected $source;

    /**
     * The source tyype
     *
     * @var string
     */
    protected $sourceType;

    /**
     * Flag to check if resource is writable
     *
     * @var bool
     */
    protected $writable;

    /**
     * Parser type
     *
     * @var string
     */
    protected $type;

    /**
     * Table configuration info
     *
     * @var array
     */
    protected $config = [];

    /**
     * The parser options
     *
     * @var array
     */
    protected $options = [];

   
    /**
     * The parsed data
     *
     * @var array
     */
    protected $data = [];

    /**
     * the columns info
     *
     * @var array
     */
    protected $columns;
    
    /**
     * The custom parser registered
     *
     * @var array
     */
    protected static $bindedParsers = [];

   
    /**
     * constructor
     *
     * @param string $database
     * @param string $table
     * @param array $tableDefinition The table defonotopn in database connections config
     */
    public function __construct(string $database, string $table,array $tableDefinition)
    {
        $this->init($database,$table,$tableDefinition);

        $this->process();

        $this->defineTableDataAndColumns();
    }
    
    /**
     * initialize the parser without reading data
     *
     * @param string $database
     * @param string $table
     * @param array $tableDefinition
     * @return void
     */
    protected function init(string $database, string $table,array $tableDefinition)
    {
        $this->database     = $database;
        $this->table        = $table;
        $this->config       = $tableDefinition;
        
        $this->source       = $this->config('source');
        $this->sourceType   = Resource::getType($this->source);

        $options            = $this->config('options',[]);
        $this->options      = array_replace_recursive($this->options,$options);
    }


    /**
     * create data from source
     *
     * @return array
     */
    abstract public function process();

    /**
     * Returns the source type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * Return current source declared
     *
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Return current source type
     *
     * @return string
     */
    public function getSourceType()
    {
        return $this->sourceType;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getDatabase()
    {
        return $this->database;
    }


    /**
     * Returns the data currently stored in table
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Create a parser and use it
     *
     * @param string $type the parser type
     * @param string $class the class where to bind the new parser
     * @return void
     */
    public static function register(string $type,string $class)
    {

        if(isset(self::$bindedParsers[$type])){
            throw new DataReaderException("{$type} is already registered as dataparser");
        }
        elseif(!class_exists($class) || is_a($class,__CLASS__)){
            throw new DataReaderException("{$type} is not a valid fpdo dataparser");
        }
        
        self::$bindedParsers[$type] = $class;
    }

    /**
     * Get the custom parse registered
     *
     * @return array
     */
    public static function getRegisteredParser(){
        return self::$bindedParsers;
    }


    /**
     * get config param or all if key is null or not defined
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key = null,$default = null)
    {
        if($key === null){
            return $this->config;
        }
        else{
            return Arr::get($this->config,$key,$default);
        }
    }

    /**
     * get options param or all if key is null or not defined
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function options(string $key = null,$default = null)
    {
        if($key === null){
            return $this->options;
        }
        else{
            return Arr::get($this->options,$key,$default);
        }
    }

    /**
     * create a valid sql table comun list, and adapt the data to the column list.
     * If data of single row is unordered will be ordered
     * All columns will be collected and invalid column names
     * wiil be converted as column<INT> regarding its position in array
     * Also according with a schema, the columns will be added/ renamed
     *
     * @return void
     */
    protected function defineTableDataAndColumns()
    {
        $tableColumns = $this->columns;

        foreach($this->data as $row){
            $columns    = array_keys($row);
            $newColumns = array_diff($columns,$tableColumns);
            if($newColumns){
                foreach($newColumns as $newColumn){
                    $tableColumns[$newColumn] = $newColumn;
                }
            }
        }

        //change column names from int to column<INT>
        $cnt = 1;
        foreach($tableColumns as $originalName=>$endName){
            if(is_int($endName) || is_float($endName) || $endName == ''){
                $tableColumns[$originalName] = config('fpdo.default_column','column').$cnt;
            }

            $cnt++;
        }

        //now we match the schema, if any
        if($this->config('schema')){

            $tableColumnsKeys   = array_keys($tableColumns);
            $schemaColumns      = [];
            foreach($this->config('schema',[]) as $schemaColName => $schemaColDef){
                if(is_int($schemaColName) && is_null($schemaColDef)){
                    $schemaColumns[] = null;
                }
                else{
                    $schemaColumns[] = [$schemaColName,$schemaColDef];
                }
            }
    
            foreach($schemaColumns as $pos=>$schemaColInfo){
                if(!is_null($schemaColInfo)){
                    if(isset($tableColumnsKeys[$pos])){
                        $tableColumns[ $tableColumnsKeys[$pos] ] = $schemaColInfo[0];
                    }
                    else{
                        $tableColumnsKeys[]                 = $schemaColInfo[0];
                        $tableColumns[$schemaColInfo[0]]    = $schemaColInfo[0];
                    }
                }
            }
        }

        //now adapt data to the new table columns data
        foreach($this->data as $k=>$row){
            $this->data[$k] = $this->adaptDataRowToTableColumns($tableColumns,$row);
        }
        

        $this->columns = $tableColumns;
    }


    public function getColumns()
    {
        return $this->columns;
    }
    
    /**
     * make data compliant with columns
     *
     * @param array $tableColumns
     * @param array $row
     * @return array
     */
    protected function adaptDataRowToTableColumns(array $tableColumns,array $row){
        $tmp = [];
        foreach($tableColumns as $originalName => $column){
            $tmp[$column] = $row[$originalName] ?? $row[$column] ?? null;
        }

        return $tmp;
    }
}

?>