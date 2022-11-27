<?php 
namespace Fpdo\Reader;

use Fpdo\Resource;
use Fpdo\DataReader;

class CsvReader extends DataReader{
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
        $sourceData = Resource::acquire($this->source);
        $sourceData = $this->removeBomUtf8(trim($sourceData));

       
        $prevLineEnding = ini_get('auto_detect_line_endings');
        
        ini_set('auto_detect_line_endings',TRUE);
        
        if($sourceData == ''){
            if($this->config('schema')){
                $sourceData = implode($this->options('delimiter'),array_keys($this->config('schema')));
            }
        }
        
        $handle = tmpfile();
        fwrite($handle,$sourceData);
        fseek($handle, 0);
        
        $cnt            = 0;
        $headers        = [];
        $rows           = [];

        while (($dataRow = fgetcsv($handle, 1000,$this->options('delimiter'),$this->options('enclosure'))) !== FALSE) {
            if($cnt == 0){
                if($this->options('use_header')){
                    $headers = $dataRow;
                }
                else{
                    $rows[] = $dataRow;
                }
            }
            else{
                $rows[] = $dataRow;
            }

            $cnt++;
        }

        
        if($headers){
            foreach($rows as $k=>$row){
                $tmp = [];
                foreach($row as $rKey=>$value){
                    if(isset($headers[$rKey])){
                        $tmp[ $headers[$rKey] ] = $value;
                    }
                    else{
                        $tmp[] = $value;
                    }
                }
                
                $rows[$k] = $tmp;
            }
        }
        
        
        fclose($handle);
        ini_set('auto_detect_line_endings',$prevLineEnding);
        
        
        $this->columns = $headers;
        $this->data = $rows;
    }

    /**
     * Removes the BOM at start of csv
     *
     * @param string  $csvContent
     * @return string
     */
    protected function removeBomUtf8(string $csvContent){
        if(substr($csvContent,0,3)==chr(hexdec('EF')).chr(hexdec('BB')).chr(hexdec('BF'))){
             return substr($csvContent,3);
         }
         else{
             return $csvContent;
         }
      }

}
