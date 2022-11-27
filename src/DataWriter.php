<?php
namespace Fpdo;
use Illuminate\Support\Arr;
use Fpdo\Exceptions\DataWriterException;
use Exception;

abstract class DataWriter{

   
    
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
     * The destination
     *
     * @var mixed
     */
    protected $destination;

    /**
     * The destination tyype
     *
     * @var string
     */
    protected $destinationType;

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
     * @param array $tableDefinition The table defonition in database connections config
     * @param array $data data to be written
     */
    public function __construct(string $database, string $table,array $tableDefinition,array $data = [])
    {
        $this->init($database,$table,$tableDefinition);
        $this->data = $data;
    }
    
    /**
     * initialize the parser
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
        
        $this->destination       = $this->config('source');
        $this->destinationType   = Resource::getType($this->destination);
        
        $options            = $this->config['options'] ?? [];
        $this->options      = array_replace_recursive($this->options,$options);

    }


    /**
     * create data for destination
     *
     * @return mixed
     */
    abstract public function process();

    /**
     * Returns the destination type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * Return current destination declared
     *
     * @return mixed
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Return current destination type
     *
     * @return string
     */
    public function getDestinationType()
    {
        return $this->destinationType;
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
            throw new DataWriterException("{$type} is already registered as datawriter");
        }
        elseif(!class_exists($class) || is_a($class,__CLASS__)){
            throw new DataWriterException("{$type} is not a valid fpdo datawriter");
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


    public function write()
    {
        $destination = $this->destination; 
        $data        = $this->process();

        // dd($destination);

        if(is_callable($destination)){
            return  call_user_func($destination,$data);
        }
        elseif(is_resource($destination) && get_resource_type($destination) == 'stream'){
            return fwrite($destination,$data);
        }
        // elseif(is_object($destination) && is_a($destination,'SimpleXMLElement',false)){

        //     return $destination->asXML();
        // }
        elseif(is_object($destination)){
            try{
                $destination->data =  $data;

                return $destination;
            }
            catch(Exception $e){
                $message = $e->getMessage();
                throw new DataWriterException("FPDO: Unable to write {$this->database}.{$this->table} output data - {$message}");
            }
        }
        elseif(is_scalar($destination)){
            if(!is_file($destination) || (is_file($destination) && is_readable($destination))){
                try{
                    return file_put_contents($destination,(string)$data);
                }
                catch(Exception $e){
                    $message = $e->getMessage();
                    throw new DataWriterException("FPDO: Unable to write {$this->database}.{$this->table} output data - {$message}");
                }
            }
            if(filter_var($destination,FILTER_VALIDATE_URL)){
                $urlInfo = parse_url($destination,PHP_URL_SCHEME);
                switch(strtolower($urlInfo)){
                    case 'http':
                    case 'https':
                        $context = stream_context_create([
                            "http" => [
                                'method'  => 'POST',
                                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                                'content' => http_build_query(['data'=>$data])
                            ],
                            "ssl"=>[
                                "verify_peer"=>false,
                                "verify_peer_name"=>false,
                            ]
                        ]);

                        return file_get_contents($destination, false, $context);
                    break;
                    case 'ftp':
                    case 'sftp':
                        return file_put_contents(
                            $destination,
                            (string)$data,
                            false,
                            stream_context_create([
                                'ftp' => ['overwrite' => true],
                                "ssl"=>[
                                    "verify_peer"       => false,
                                    "verify_peer_name"  => false,
                                ]
                            ])
                        );
                    break;
                    default:
                        throw new DataWriterException("FPDO: unable to detect correct output source for {$this->database}.{$this->table}");
                    break;
                }            
            }
        }
        elseif(is_array($destination)){
            return $this->data;
        }
    }
}