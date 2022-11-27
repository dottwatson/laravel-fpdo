<?php
namespace Fpdo;
use PDO;

class DataGuesser{

    /**
     * determinate the data tipe in array
     * the more frequency of a specified type wins on all
     *
     * @param array $values
     * @return array
     */
    public static function checkType(array $values = []){
        if(!$values){
            return ['type'=>'NULL','length'=>0];
        }

        $types = array_map(function($item){
            return  gettype($item);
        },$values);

        $occurrences = array_count_values($types);

        arsort($occurrences,SORT_NUMERIC);

        if(!function_exists('array_key_first')){
            function array_key_first($data)
            {
                $keys = array_keys($data);
                return (isset($keys[0]))?$keys[0]:null;
            }
        }

        //if there are only occurences of integer and doubles, the column is surely double
        //this cover the case when in testing data exists a lot of integers
        if(array_keys($occurrences) == ['integer','double']){
            $type = 'double';
        }
        else{
            $type = array_key_first($occurrences);
        }

        //if is present a string,it wins ever on other types
        //this cover the case when in varchar column are present numbers
        if($type != 'string' && isset($occurrences['string']) ){
            $type = 'string';
        }

        //we have the type: now we check the maximum length
        $length = null;
        array_walk(function($value) use ($type,&$length){
            if(gettype($value) == $type){
                // if($type == 'double' || $type == 'decimal'){
                //     list($ints,$decs) = explode('.',$value,2);
                //     if(is_null($length)){
                //         $length = '0.0';
                //     }
                //     list($lengthInts,$lengthDecs) = explode('.',$length,2);
                //     $length = strlen($value);
                //     if(strlen($ints) > $lengthInts){
                //         $lengthInts = str_pad('',strlen($ints),'0');
                //     }
                //     if(strlen($decs) > $lengthDecs){
                //         $lengthDecs = str_pad('',strlen($decs),'0');
                //     }

                //     $length = "{$lengthInts}.{$lengthDecs}";
                // }
                // elseif($type == 'integer' && strlen($value) > $length){
                //     $length = strlen($value);
                // }
                // elseif(strlen((string)$value) > $length){
                //     $length = strlen((string)$value);
                // }
                if($type == 'double'){

                }
                elseif($type == 'integer' && strlen($value) > $length){
                    $length = strlen($value);
                }
                elseif(strlen((string)$value) > $length){
                    $length = strlen((string)$value);
                }

            }
        },$values);

        return ['type'=>$type,'length'=>$length];
    }


    public static function makeSQlColumn(PDO $pdoInstance, array $values = [],array $customDefinition = [])
    {

        //define nullable column
        $nullable = (isset($customDefinition['null']))
            ?(bool)$customDefinition['null']
            :(in_array(null,$values));

        $sqlNull = ($nullable)?'':'NOT NULL';

        //define autoincrement
        $sqlAutoincrement = (isset($customDefinition['auto_increment']))
            ?'AUTO_INCREMENT'
            :'';

        //define charset
        $sqlCollation = (isset($customDefinition['charset']))
            ?'CHARACTER SET '.$customDefinition['charset']
            :'';

        //define collation
        $sqlCharset = (isset($customDefinition['collation']))
            ?'COLLATE '.$customDefinition['collation']
            :'';

        //define default value
        $sqlDefault = (isset($customDefinition['default']))
            ?'DEFAULT '.$pdoInstance->quote($customDefinition['default'])
            :'';

        if(isset($customDefinition['type'])){
            $customDefinedColumn = true;
            $tmpType = $customDefinition['type'];
            if(is_array($tmpType)){
                $type   = (string)$tmpType[0];
                $length = $tmpType[1] ?? null;
            }
            else{
                $type = (string)$customDefinition['type'];
                $length = null;
            }
        }
        else{
            $customDefinedColumn = false;
            
            $typeCheck = static::checkType($values);
            extract($typeCheck);
            $length = ($type == 'NULL')?1000:$length;
            $type   = ($type == 'NULL')?'string':$type;
        }

        // Possibles values for the returned string are: 
        // "boolean" "integer" 
        // "double" (for historical reasons "double" is returned in case of a float, and not simply "float") 
        // "string" "array" "object" "resource" "NULL" "unknown type" 
        // "resource (closed)" since 7.2.0
        $sqlType = '';
        switch($type){
            case 'integer':
                if($length <= 11){
                    $sqlType = 'INT(11)';
                }
                elseif($length <= 20){
                    $sqlType = 'BIGINT(20)';
                }
                else{
                    $sqlType = 'BIGINT(50)';
                }
            break;
            case 'boolean':
                $sqlType = 'TINYINT(1)';
            break;
            case 'double':
            case 'float':
                if($customDefinedColumn){
                    $length     = ($length !== null)?$length:'00000.00000';
                    $blocks     = explode('.',$length,2);
                    $length     = strlen($length)-1;
                    $decimals   = strlen($blocks[1] ?? '');
                }
                else{
                    $length     = 0;
                    $decimals   = 0;
                    array_map(function($value)use(&$length,&$decimals){
                        if(strlen($value) -1 > $length){
                            $length = strlen($value) -1;
                        }
                        
                        $blocks = explode('.',$value,2);
                        if(isset($blocks[1])){
                            if(strlen($blocks[1]) > $decimals){
                                $decimals = strlen($blocks[1]);
                            }
                        }
                    },$values);
                }

                $sqlType = "DOUBLE({$length},{$decimals})";
            break;
            case 'NULL':
                $sqlType = 'VARCHAR(255)';
            break;
            case 'array':
            case 'object':
                $sqlType = 'JSON';
            break;
            default:
                $length = ((int)$length < 255)?255:(int)$length;
                if($length > 255){
                    $sqlType = 'TEXT';
                }
                else{
                    $sqlType = 'VARCHAR('.$length.')';
                }
            break;
        }
        

        $columnDefinition =[
            $sqlType,
            $sqlNull,
            $sqlCharset,
            $sqlCollation,
            $sqlAutoincrement,
            $sqlDefault
        ];

        foreach($columnDefinition as $k=>$definitionParam){
            if($definitionParam == ''){
                unset($columnDefinition[$k]);
            }
        }

        return trim(implode(' ',$columnDefinition));
    }

}