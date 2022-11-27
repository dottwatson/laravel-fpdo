<?php 
namespace Fpdo\Writer;

use Fpdo\Resource;
use Fpdo\DataWriter;

class CsvWriter extends DataWriter{
    protected $type = 'csv';

    protected $options = [
        'delimiter'     => ';',
        'enclosure'     => '"',
        'use_header'    => false,
    ];
    

    /**
     * @inheritDoc
     */
    public function process()
    {
        $csvRows = [];

        $cnt = 0;
        foreach($this->data as $row){
            if($this->options('use_header') && $cnt == 0 ){
                $header = array_keys($row);
                $csvRows[] = $this->makeExportRow($header);         
            }

            $csvRows[] = $this->makeExportRow($row); 
            $cnt++;
        }

        return implode("\n",$csvRows);
    }

    /**
     * generate a weel formatted csv row
     *
     * @param array $row
     * @return string
     */
    protected function makeExportRow(array $row)
    {
        foreach($row as $k=>$value){
            if(is_object($value) || is_array($value)){
                $value = json_encode($value);
            }

            $value = str_replace(["\n","\r","\r\n"],'',$value);
            if(!$this->isNumber($value) && $value != ''){
                $value = $this->options('enclosure').$value.$this->options('enclosure');
            }
            
            $row[$k]    = $value;
        }

        $row = implode($this->options('delimiter'),$row);
        return $row;
    }

    /**
     * check if the value is a valid number or not
     *
     * @param mixed $value
     * @return boolean
     */
    protected function isNumber($value)
    {
        $value      = trim((string)$value);
        $isNumber   =  preg_match('#^[0-9]+(\.[0-9]+)?$#',$value);

        return $isNumber;
    }

}
