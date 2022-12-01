<?php
namespace Fpdo;

use Illuminate\Support\ServiceProvider;
use Fpdo\Database\Connection as FpdoConnector;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;

class FpdoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/fpdo.php' => config_path('fpdo.php'),
        ]);        
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        //fix the missing database name for connections with fpdo as driver
        //if missed, it will be the snake version of the connection name
        //this is necessair ever
        foreach(config('database.connections',[]) as $connectionName=>$connectionInfo){
            if(Arr::get($connectionInfo,'driver') === 'fpdo' && !Arr::get($connectionInfo,'database')){
                config([
                    "database.connections.{$connectionName}.database"=>Str::snake($connectionName) 
                ]);
            }
        }

        //register the driver into the system, so it will be available globally
        Connection::resolverFor('fpdo',function($connection, $database, $prefix, $config){
           
            $dbname = Arr::get($config,'database');
            $prefix = Arr::get( $config,'prefix','');

            //This is the default character set for the database collation
            $charset            = Arr::get($config,'charset',config('fpdo.default_charset','utf8'));
            $connectinOptions   = Arr::get($config,'options',[]);

            //due to its nature, the databasec credentials are not necessaire.
            //So we create fake username and password to accomplish PDO 
            $username   = Str::random(8);
            $password   = Str::random(8);

            $dsn        = "fpdo:host=127.0.0.1;dbname={$dbname};";
            $pdoCls     = 'Fpdo\\Php'.PHP_MAJOR_VERSION.'\\Fpdo';
            
            $instance = new $pdoCls($dsn,$username,$password);

            foreach($connectinOptions as $optName=>$optVal){
                $instance->setAttribute($optName,$optVal);
            }

            $instance->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

            $connector = new FpdoConnector($instance,$dbname,$prefix,$config);

            //force the pdo reader
            $connector->setReadPdo($instance);

            return $connector;
        });


    }
}
