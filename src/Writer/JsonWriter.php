<?php 
namespace Fpdo\Writer;

use Fpdo\Resource;
use Fpdo\DataWriter;
use Fpdo\Exceptions\DataWriterException;

class JsonWriter extends DataWriter{
    protected $type = 'json';
    
    public function process()
    {
        $data = $this->toUTF8($this->data);

        $data = json_encode($data,JSON_PRETTY_PRINT);
        if(json_last_error() !== JSON_ERROR_NONE){
            throw new DataWriterException("FPDO: Error writing {$this->database}.{$this->table} as json: ".json_last_error_msg());
        }

        return $data;
    }
    
    /**
     * convert string or array into utf8
     *
     * @param mixed $data
     * @return mixed
     */
    public function toUTF8($data)
    {
        if (is_string($data)){
            return utf8_encode($data);
        }
        if (!is_array($data)){
            return $data;
        }
        
        $values = [];
        foreach ($data as $k=>$value)
            $values[$k] = $this->toUTF8($value);
        return $values;
    }
}

?>