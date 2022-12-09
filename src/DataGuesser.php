<?php
namespace Fpdo;

use Illuminate\Support\Carbon;
use PDO;

class DataGuesser{

    public static function getValueType($value)
    {
        $varType = strtolower(gettype($value));
        if($varType == 'string'){
            if((string)((int)$value) === $value){
                $varType = 'integer';
            }
            elseif((string)((float)$value) === $value){
                $varType = 'integer';
            }
            //timestamp?
            elseif(preg_match('#[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}#',$value)){
                try{
                    $date = Carbon::parse($value);
                    $varType = 'timestamp';
                }
                catch(\Exception $e){}
            }
            //date?
            elseif(preg_match('#[0-9]{4}-[0-9]{2}-[0-9]{2}#',$value)){
                try{
                    $date = Carbon::parse($value);
                    $varType = 'date';
                }
                catch(\Exception $e){}
            }
            //time?
            elseif(preg_match('#[0-9]{2}:[0-9]{2}:[0-9]{2}#',$value)){
                try{
                    $date = Carbon::parse($value);
                    $varType = 'time';
                }
                catch(\Exception $e){}
            }
            //json?
            else{
                try{
                    $testJson =  json_decode($value,true,512,JSON_THROW_ON_ERROR);
                    $varType = 'json';
                }
                catch(\Exception $e){}
            }
        }
        elseif($varType == 'undefined'){
            $varType = 'string';
        }
        elseif($varType == 'double'){
            $varType = 'float';
        }
        elseif($varType == 'resource (closed)'){
            $varType = 'resource';
        }


        return $varType;
    }
    
    
    /**
     * determinate the data type in array
     * the more frequency of a specified type wins on all
     *
     * @param array $values
     * @return array
     */
    public static function guessSqlType(array $values = []){
        if(!$values){
            return ['type'=>'text','length'=>-1];
        }

        $maxLengths = [];
        $types = array_map(function($item) use(&$maxLengths){
            $matchedType                = DataGuesser::getValueType($item);
            $maxLengths[$matchedType]   = static::getMaxLenghtVar($item,$matchedType, $maxLength[$matchedType] ?? null);
            return  $matchedType;
        },$values);


        $occurrences = array_count_values($types);

        arsort($occurrences,SORT_NUMERIC);

        $allAvailableTypes  = array_keys($occurrences);
        $isNullable         = in_array('null',$allAvailableTypes);

        
        //now remove the null if exists and check only defined values
        $realTypes = $occurrences;
        unset($realTypes['null']);
        $allAvailableTypes  = array_keys($realTypes);

        
        $maxLength = 0;
        //string ever win on all
        if(in_array('string',$allAvailableTypes)){
            $resultType = 'text';
            $maxLength  = $maxLengths['string'] ?? 1000;
        }
        //if is integer and float then is float
        elseif($allAvailableTypes == ['integer','float'] || $allAvailableTypes == ['float','integer']){
            $resultType = 'float';
            $maxLength  = $maxLengths['float'] ?? [5,5];
            $maxLength  = str_pad('',count($maxLength[0])).'.'.str_pad('',count($maxLength[1]));
        }
        else{
            $resultType = array_shift($allAvailableTypes);
            if(in_array($resultType,['resource','array','object'])){
                $resultType = 'json';
            }
            elseif($resultType == 'undefined' || $resultType == 'null'){
                $resultType = 'text';
                $maxLength  = $maxLengths['string'] ?? 1000;
            }
            elseif($resultType == 'integer'){
                $maxLength  = $maxLengths['integer'] ?? 11;
            }
        }

        return ['type'=>$resultType,'nullable'=>$isNullable,'length'=>$maxLength];
    }


    public static function makeSQlColumn(PDO $pdoInstance,string $columnName = null, array $values = [],array $customDefinition = [])
    {

        if(config('fpdo.guess_datatype',true) == false){
            return "TEXT NULL DEFAULT ''";
        }
        
        //define nullable column
        $nullable = (isset($customDefinition['nullable']))
            ?(bool)$customDefinition['nullable']
            :(in_array(null,$values));

        $sqlNull = ($nullable)?'NULL':'NOT NULL';


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



        $typeCheck  = static::guessSqlType($values);
        $length     = $typeCheck['length'];
        $type       = $typeCheck['type'];
        $sqlNull    = ($typeCheck['nullable'])?'NULL':'NOT NULL';

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
            case 'float':
                $length     = ($length !== null)?$length:'00000.00000';
                $blocks     = explode('.',$length,2);
                $length     = strlen($length)-1;
                $decimals   = strlen($blocks[1] ?? '');

                $sqlType = "DOUBLE({$length},{$decimals})";
            break;
            case 'null':
                $sqlType = 'VARCHAR(255)';
            break;
            case 'json':
                $sqlType = 'JSON';
            break;
            case 'text':
                if(config('fpdo.guess_datatype') == true){
                    $length = ($length <=255) ?255:$length;
                }

                if($length > 255){
                    $sqlType = 'TEXT';
                }
                else{
                    $sqlType = 'VARCHAR('.$length.')';
                }
            break;
            default:
                $sqlType = 'TEXT';
                $sqlNull = 'NULL';
                $sqlDefault = '';
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

        return ['column' => $columnName,'sql'=>trim(implode(' ',$columnDefinition)),'structure' => $columnDefinition];
    }

    /**
     * calculate the current max length of filed by its type, so we can better define the column
     *
     * @param mixed $var
     * @param string $type
     * @param mixed $comparedWith
     * @return int|array|null
     */
    protected static function getMaxLenghtVar($var,$type,$comparedWith = null)
    {
        $varLength = null;
        switch($type){
            case 'integer':
            case 'string':
                $varLength = strlen((string)$var);
            break;
            case 'float':
                $itemBlocks = explode('.',$var,2);
                $ints = strlen($itemBlocks[0]);
                $decs = strlen($itemBlocks[1] ?? '');

                $varLength =  [$ints,$decs];
                $varLength =  strlen($var);
            break;
            default:
                $varLength =  0;
            break;
        }

        if($comparedWith !== null){
            switch($type){
                case 'integer':
                case 'string':
                    $comparedLength = strlen((string)$comparedWith);
                    if($comparedLength > $comparedLength){
                        $varLength = $comparedLength;
                    }
                break;
                case 'float':
                    if($comparedWith[0] > $varLength[0]){
                        $varLength[0] = $comparedWith[0];
                    }

                    if($comparedWith[1] > $varLength[1]){
                        $varLength[1] = $comparedWith[1];
                    }
                break;
                default:
                    $varLength =  0;
                break;
            }
        }

        return $varLength;
    }

}