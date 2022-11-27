<?php 
namespace Fpdo\Writer;

use Fpdo\DataWriter;

class ArrayWriter extends DataWriter{
    protected $type = 'array';

    /**
     * @inheritDoc
     */
    public function process()
    {
        return $this->data;
    }


}

?>