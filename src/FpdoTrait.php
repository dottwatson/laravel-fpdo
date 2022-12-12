<?php
namespace Fpdo;

use Vimeo\MysqlEngine\FakePdoTrait;
use Fpdo\Server;
use Fpdo\DataGuesser;

trait  FpdoTrait{
    use FakePdoTrait{
        FakePdoTrait::__construct as parentConstruct;
    }


    public function __construct(string $dsn, string $username = '', string $passwd = '', array $options = [],string $connectionName)
    {
       
        //$this->real = new \PDO($dsn, $username, $passwd, $options);

        $dsn = \Nyholm\Dsn\DsnParser::parse($dsn);
        $host = $dsn->getHost();

        if (preg_match('/dbname=([a-zA-Z0-9_]+);/', $host, $matches)) {
            $this->databaseName = $matches[1];
        }

        // do a quick check for this string – hacky but fast
        $this->strict_mode = \array_key_exists(\PDO::MYSQL_ATTR_INIT_COMMAND, $options)
            && \strpos($options[\PDO::MYSQL_ATTR_INIT_COMMAND], 'STRICT_ALL_TABLES');

        $this->server = Server::getOrCreate($connectionName);
        
        $this->server->setPdo($this);
    }


    // /**
    //  * try to generate a sql query for table creation, based on a recordset.
    //  * It reads each record, extract keys as columns an detect the type and if nullable
    //  *
    //  * @param string $name
    //  * @param array $rows
    //  * @param array $culumConfig
    //  * @param FakePDO $pdo
    //  * 
    //  * @return string
    //  */
    // public static function buildTableData(string $name,array $rows = [],array $columnsConfig = [],$pdoInstance,string $tableCharset = null)
    // {
    //     $columns = [];
    //     $sqlColumns = [];

    //     $tableCharset = $tableCharset ?? config('fpdo.default_charset','utf8mb4');

    //     if($rows){
    //         foreach($rows as $row){
    //             if(is_object($row)){
    //                 $rowData  = get_object_vars($row);
    //             }
    //             elseif(is_array($row)){
    //                 $rowData = $row;
    //             }
    //             else{
    //                 continue;
    //             }

    //             $rowKeys = array_keys($rowData);
    //             $columns = $columns + $rowKeys;
    //         }
    //     }
    //     else{
    //         $columns = array_keys($columnsConfig);
    //     }


    //     foreach($columns as $columnName){
    //         $columnValues   = array_column($rows,$columnName);
    //         $columnValues   = array_map(function($value){
    //             if( (string)((int)$value) == (string)$value){
    //                 return (int)$value;
    //             }
    //             elseif((string)((float)$value) == (string)$value){
    //                 return (float)$value;
    //             }
    //             else{
    //                 return $value;
    //             }
    //         },$columnValues);

    //         $sqlColumns[]   = "    ".self::guessColumnType($columnName,$columnValues,$columnsConfig,$pdoInstance);
    //     }

    //     $sql = "CREATE TABLE {$name} (\n".implode(",\n",$sqlColumns)." \n) CHARACTER SET {$tableCharset}";

    //     return $sql;
    // }

    // /**
    //  * try to read a set of values and generate a valid database column type
    //  *
    //  * @param string $columnName
    //  * @param array $columnValues
    //  * @return string
    //  */
    // protected static function guessColumnType(string $columnName,array $columnValues,array $columnsConfig,$pdoInstance)
    // {
    //     $currentColumnConfig = (isset($columnsConfig[$columnName]))?$columnsConfig[$columnName]:[];

    //     //define nullable column
    //     $nullable = (isset($currentColumnConfig['null']))
    //         ?(bool)$currentColumnConfig['null']
    //         :(in_array(null,$columnValues));

    //     $sqlNull = ($nullable)?'':'NOT NULL';

    //     //define autoincrement
    //     $sqlAutoincrement = (isset($currentColumnConfig['auto_increment']))
    //         ?'AUTO_INCREMENT'
    //         :'';

    //     //define charset
    //     $sqlCollation = (isset($currentColumnConfig['charset']))
    //         ?'CHARACTER SET '.$currentColumnConfig['charset']
    //         :'';

    //     //define collation
    //     $sqlCharset = (isset($currentColumnConfig['collation']))
    //         ?'COLLATE '.$currentColumnConfig['collation']
    //         :'';

    //     //define default value
    //     $sqlDefault = (isset($currentColumnConfig['default']))
    //         ?'DEFAULT '.$pdoInstance->quote($currentColumnConfig['default'])
    //         :'';

    //     if(isset($currentColumnConfig['type'])){
    //         $customDefinedColumn = true;
    //         $tmpType = $currentColumnConfig['type'];
    //         if(is_array($tmpType)){
    //             $type   = (string)$tmpType[0];
    //             $length = $tmpType[1] ?? null;
    //         }
    //         else{
    //             $type = (string)$currentColumnConfig['type'];
    //             $length = null;
    //         }
    //     }
    //     else{
    //         $customDefinedColumn = false;
    //         //try to determinate the data type
    //         $types = array_map(function($item){
    //             return  gettype($item);
    //         },$columnValues);
    
    //         $occurrences = array_count_values($types);

    //         arsort($occurrences,SORT_NUMERIC);
    
    //         if(!function_exists('array_key_first')){
    //             function array_key_first($data)
    //             {
    //                 $keys = array_keys($data);
    //                 return (isset($keys[0]))?$keys[0]:null;
    //             }
    //         }
    
    //         //if there are only occurences of integer and doubles, the column is surely double
    //         //this cover the case when in double column exists a lot of integers
    //         if(array_keys($occurrences) == ['integer','double']){
    //             $type = 'double';
    //         }
    //         else{
    //             $type = array_key_first($occurrences);
    //         }

    //         //if is present a string,it wins ever on other types
    //         //this cover the case when in varchar column are present numbers
    //         if($type != 'string' && isset($occurrences['string']) ){
    //             $type = 'string';
    //         }
    
    //         $length = 0;
    //         array_map(function($value)use(&$length){
    //             if(is_array($value) || is_object($value)){
    //                 return;
    //             }
    //             $valueLength = strlen((string)$value);
    //             if($valueLength > $length){
    //                 $length = $valueLength;
    //             }
    //         },$columnValues);
    //     }
        
    //     // Possibles values for the returned string are: 
    //     // "boolean" "integer" 
    //     // "double" (for historical reasons "double" is returned in case of a float, and not simply "float") 
    //     // "string" "array" "object" "resource" "NULL" "unknown type" 
    //     // "resource (closed)" since 7.2.0
    //     $sqlType = '';
    //     switch($type){
    //         case 'integer':
    //             if($length <= 11){
    //                 $sqlType = 'INT(11)';
    //             }
    //             elseif($length <= 20){
    //                 $sqlType = 'BIGINT(20)';
    //             }
    //             else{
    //                 $sqlType = 'BIGINT(50)';
    //             }
    //         break;
    //         case 'boolean':
    //             $sqlType = 'TINYINT(1)';
    //         break;
    //         case 'double':
    //         case 'float':
    //             if($customDefinedColumn){
    //                 $length     = ($length !== null)?$length:'00000.00000';
    //                 $blocks     = explode('.',$length,2);
    //                 $length     = strlen($length)-1;
    //                 $decimals   = strlen($blocks[1] ?? '');
    //             }
    //             else{
    //                 $length     = 0;
    //                 $decimals   = 0;
    //                 array_map(function($value)use(&$length,&$decimals){
    //                     if(strlen($value) -1 > $length){
    //                         $length = strlen($value) -1;
    //                     }
                        
    //                     $blocks = explode('.',$value,2);
    //                     if(isset($blocks[1])){
    //                         if(strlen($blocks[1]) > $decimals){
    //                             $decimals = strlen($blocks[1]);
    //                         }
    //                     }
    //                 },$columnValues);
    //             }

    //             $sqlType = "DOUBLE({$length},{$decimals})";
    //         break;
    //         case 'NULL':
    //             $sqlType = 'VARCHAR(255)';
    //         break;
    //         case 'array':
    //         case 'object':
    //             $sqlType = 'JSON';
    //         break;
    //         default:
    //             $length = ((int)$length < 255)?255:(int)$length;
    //             if($length > 255){
    //                 $sqlType = 'TEXT';
    //             }
    //             else{
    //                 $sqlType = 'VARCHAR('.$length.')';
    //             }
    //         break;
    //     }
        

    //     $columnDefinition =[
    //         $columnName,
    //         $sqlType,
    //         $sqlNull,
    //         $sqlCharset,
    //         $sqlCollation,
    //         $sqlAutoincrement,
    //         $sqlDefault
    //     ];

    //     foreach($columnDefinition as $k=>$definitionParam){
    //         if($definitionParam == ''){
    //             unset($columnDefinition[$k]);
    //         }
    //     }

    //     return trim(implode(' ',$columnDefinition));

    // }


    /**
     * Try to determinate the data type by string syntax
     * resolved types are: INT,DOUBLE,VARCHAR,TEXT,DATE,TIMESTAMP,TIME,BOOLEAN
     * other datattypes can be specified in the schema of table
     * during its definition
     *
     * @param [type] $value
     * @return string
     */
    protected function detectedDatabseDataTypeByValue($value)
    {
        $value = (string)$value;
        $value = trim($value);
        if(preg_match('#^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$#',$value)){
            return 'TIMESTAMP';
        }

        if(preg_match('#^[0-9]{4}-[0-9]{2}-[0-9]{2}$#',$value)){
            return 'DATE';
        }


        if(preg_match('#^[0-9]{2}:[0-9]{2}:[0-9]{2}$#',$value)){
            return 'TIME';
        }

        if((string)((int)$value) === $value){
            return 'INT';
        }

        if((string)((float)$value) === $value){
            return 'DOUBLE';
        }

        if(is_bool($value)){
            return 'BOOLEAN';
        }
    }
}