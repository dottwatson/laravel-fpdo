<?php 
namespace Fpdo\Writer;

use Fpdo\Exceptions\DataWriterException;
use Fpdo\DataWriter;

use DOMDocument;
use DOMNamedNodeMap;
use DOMNode;
use DOMImplementation;

class XmlWriter extends DataWriter{
    protected $type = 'xml';

    /**
     * @var DOMDocument
     */
    private $xml = null;



    /**
     * @inheritDoc
     */
    public function process()
    {
        $this->makeXml();

        foreach($this->data as $rKey=>$row){
            foreach($row as $fKey=>$fValue){
                $this->data[$rKey][$fKey] = $this->safeJsonDecode($fValue);
            }
        }

        $nodes = $this->addNode($this->table,['row'=>$this->data]);
        $this->xml->appendChild($nodes);
        
        $xmlString = $this->xml->saveXML();

        return $xmlString;
    }

    /**
     * create main xml
     *
     * @return void
     */
    protected function makeXml()
    {
        $version                    = $this->options('version','1.0');
        $encoding                   = $this->options('encoding','utf-8');
        $this->xml                  = new DomDocument($version, $encoding);
        $this->xml->xmlStandalone   = false;
        $this->xml->formatOutput    = true;
        $this->encoding             = $encoding;

        $docType = $this->options('doctype',[]);
        if(is_array($docType) && $docType){
            $this->xml->appendChild(
                (new DOMImplementation())
                    ->createDocumentType(
                        $docType['name'] ?? '',
                        $docType['publicId'] ?? '',
                        $docType['systemId'] ?? ''
                    )
            );

        }
    }


   /**
     * Convert an Array to XML.
     *
     * @param string $nodeName - name of the root node to be converted
     * @param array  $nodeData - array to be converted
     *
     * @return DOMNode
     * @throws Exception
     */
    private function addNode($nodeName, $nodeData = [])
    {
        $node = $this->xml->createElement($nodeName);

        if (is_array($nodeData)) {
            // get the attributes first.;
            if (array_key_exists('nodeAttributes', $nodeData) && is_array($nodeData['nodeAttributes'])) {
                foreach ($nodeData['nodeAttributes'] as $key => $value) {
                    if (!self::isValidTagName($key)) {
                        throw new DataWriterException('FPDO: xmlwriter detected an illegal character in attribute name. attribute: '.$key.' in node: '.$nodeName);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($nodeData['nodeAttributes']); //remove the key from the array once done.
            }

            // check if it has a value stored in nodeValue, if yes store the value and return
            // else check if its directly stored as string
            if (array_key_exists('nodeValue', $nodeData)) {
                if($this->maybeCData($nodeData['nodeValue'])){
                    $node->appendChild(
                        $this->xml->createCDATASection(
                            $this->bool2str($nodeData['nodeValue'])
                        )
                    );
                }
                else{
                    $node->appendChild(
                        $this->xml->createTextNode(
                            $this->bool2str($nodeData['nodeValue'])
                        )
                    );
                }
                //return from recursion, as a note with value cannot have child nodes.
                unset($nodeData['nodeValue']);    //remove the key from the array once done.
                return $node;
            }
        }

        //create subnodes using recursion
        if (is_array($nodeData)) {
            // recurse to get the node for that key
            foreach ($nodeData as $key => $value) {
                if (!self::isValidTagName($key)) {
                    throw new DataWriterException('FPDO: xmlwriter detected an illegal character in tag name. tag: '.$key.' in node: '.$nodeName);
                }
                if (is_array($value) && is_numeric(key($value))) {
                    // MORE THAN ONE NODE OF ITS KIND;
                    // if the new array is numeric index, means it is array of nodes of the same kind
                    // it should follow the parent key name
                    foreach ($value as $k => $v) {
                        $node->appendChild($this->addNode($key, $v));
                    }
                } else {
                    // ONLY ONE NODE OF ITS KIND
                    $node->appendChild($this->addNode($key, $value));
                }
                unset($nodeData[$key]); //remove the key from the array once done.
            }
        }

        // after we are done with all the keys in the array (if it is one)
        // we check if it has any text value, if yes, append it.
        if (!is_array($nodeData)) {
            if($this->maybeCData($nodeData)){
                $node->appendChild(
                    $this->xml->createCDATASection(
                        $this->bool2str($nodeData)
                    )
                );
            }
            else{
                $node->appendChild(
                    $this->xml->createTextNode(
                        $this->bool2str($nodeData)
                    )
                );
            }
        }

        return $node;
    }

    /**
     * Get string representation of boolean value.
     *
     * @param mixed $value
     * @return string
     */
    private  function bool2str($value)
    {
        //convert boolean to text value.
        $value = $value === true ? 'true' : $value;
        $value = $value === false ? 'false' : $value;

        return $value;
    }

    /**
     * try to convert json
     *
     * @param mixed $value
     * @return void
     */
    protected function safeJsonDecode($value)
    {
        $result = $value;

        if(!is_string($value)){
            return $result;
        }

        try{
            $decoded = json_decode($value,true);
            $result =  ($decoded === null)
                ?$value
                :$decoded;
        }
        catch(\Exception $e){}

        return $result;
    }
    
    /**
     * Check if the tag name or attribute name contains illegal characters
     * Ref: http://www.w3.org/TR/xml/#sec-common-syn.
     *
     * @param string $tag
     * @return bool
     */
    private  function isValidTagName($tag)
    {
        return preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $tag, $matches) && $matches[0] == $tag;
    }


    private function maybeCData($value)
    {
        if(strpos($value,'>') !== false || strpos($value,'<') !== false){
            return true;    
        }

        return false;
    }

}

