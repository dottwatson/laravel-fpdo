<?php 
namespace Fpdo\Reader;

use Fpdo\Resource;
use Fpdo\DataReader;
use Fpdo\Exceptions\DataReaderException;

class JsonReader extends DataReader{
    protected $type = 'json';

    /**
     * @inheritDoc
     */
    public function process()
    {
        $sourceData = Resource::acquire($this->source);
        
        if($sourceData === $this->source){
            $data = json_decode($sourceData,true);
            if(!is_array($data)){
                $data = [];
            }
        }
        else{
            $data = json_decode($sourceData,true);
            if(json_last_error() !== JSON_ERROR_NONE){
                throw new DataReaderException("Error reading {$this->database}.{$this->table} as json: ".json_last_error_msg());
            }
        }
        
        $this->data = $data;
    }
}

?>