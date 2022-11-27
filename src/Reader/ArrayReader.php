<?php 
namespace Fpdo\Reader;

use Fpdo\Resource;
use Fpdo\DataReader;
use Fpdo\Exceptions\DataReaderException;

class ArrayReader extends DataReader{
    protected $type = 'array';

    /**
     * @inheritDoc
     */
    public function process()
    {
        $data = $this->getSource();

        if(!is_array($data)){
            throw new DataReaderException("FPDO: Unable to read data for {$this->database}.{$this->table}");
        }

        $this->data = json_decode(json_encode($data),true);
    }
}

?>