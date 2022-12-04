<?php

namespace Fpdo;

use Fpdo\Exceptions\FpdoException;
use Illuminate\Support\Facades\DB;

class Fpdo{

    /**
     * returns a valid Fpdo connection
     *
     * @param string $name
     * @return \Fpdo\Database\Connection
     */
    public static function connection(string $name)
    {
        if(config("database.connections.{$name}.driver") != 'fpdo'){
            throw new FpdoException("Unable to find a valid fpdo connection named {$name}");
        }

        return DB::connection($name);
    }

    public static function getConnections()
    {
        $connections = [];
        foreach(config("database.connections",[]) as $connectionName => $connection){
            if(($connection['driver'] ?? null) == 'fpdo'){
                $connections[] = $connectionName;
            }    
        }

        return $connections;
    }

    public static function dump(array $options = [],string $connection = null)
    {

        $options = array_replace_recursive([
            'table' => '',
            'with_database'         => false,
            'with_drop_table'       => true,
            'with_truncate_table'   => true,
            'with_create_table'     => true,
            'with_insert'           => true,
            'with_insert_ignore'    => true
        ]);


    }

    public static function dumpTable(string $connection, string $table, array $options = [],$fromMasterprocess = false)
    {
        $instance   = static::connection($connection);
        $pdo        = $instance->getPdo();
        $server     = $pdo->getServer();

        $dumpResult = [];
        if(!$fromMasterprocess){
            $dumpResult[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"';
            $dumpResult[] = 'START TRANSACTION';
            $dumpResult[] = 'SET time_zone = "+00:00"';
            $dumpResult[] = '';
            $dumpResult[] = '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */';
            $dumpResult[] = '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */';
            $dumpResult[] = '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */';
            $dumpResult[] = '/*!40101 SET NAMES utf8mb4 */';
        }

        
        $options = array_replace_recursive([
            'with_database'         => false,
            'with_drop_table'       => true,
            'with_truncate_table'   => true,
            'with_create_table'     => true,
            'with_insert'           => true,
            'with_insert_ignore'    => true,
        ],$options);
        
        //because the tables are lazy loaded,emulate a query to make the table present
        $fakeQuery  = $instance->table($table)->where($instance->raw(1),'!=',1)->get();

        //retrieve columns
        $columns    = $server->getTableDefinition($connection,$table)->columns;
        $sqlColumns = [];

        collect($columns)->each(function($item,$key) use (&$sqlColumns){
            $sqlColumns[] = "`{$key}`";
        });


        if($options['with_database']){
            $dumpResult[] = "USE `{$connection}`";
        }

        if($options['with_drop_table']){
            $dumpResult[] = "DROP TABLE IF EXISTS `{$table}`";
        }

        if($options['with_create_table']){
            $schemaQuery = $server->tablesCreationQuery[$table];
            $schemaQuery = preg_replace("#CREATE TABLE#i","CREATE TABLE IF NOT EXISTS",$schemaQuery,1);

            $dumpResult[] = $schemaQuery;

        }

        if($options['with_truncate_table']){
            $dumpResult[] = "TRUNCATE TABLE `{$table}`";
        }

        if($options['with_insert'] || $options['with_insert_ignore']){
            $insert = [];
            $values = [];

            $insert = ($options['with_insert'])
                ?["INSERT INTO {$table}(".implode(', ',$sqlColumns).")","VALUES"]
                :["INSERT IGNORE INTO {$table}(".implode(', ',$sqlColumns).")","VALUES"];

            foreach( $instance->table($table)->select('*')->cursor() as $row ){
                $dataRow = get_object_vars($row);
                foreach($dataRow as $k=>$value){
                    if(is_string($value)){
                        $value = str_replace("'", "''", $value);
                        $value = $pdo->quote($value);
                    }
                    $dataRow[$k] = $value;
                }

                $values[] = '('.implode(", ",$dataRow).')';
            }

            if($values){
                $chunkSize = $options['with_insert_limit'] ?? config('fpdo.dump.insert_limit',1000);
                $values = array_chunk($values, $chunkSize );
                foreach($values as $blockValues){
                    $dumpResult[] = 
                        implode("\n",$insert).
                        "\n".
                        implode(",\n",$blockValues);
                }                
            }
            
            return implode(";\n\n",$dumpResult);
        }
   }    

}