<?php 
namespace Fpdo\Reader;

use Fpdo\DataReader;
use Fpdo\Exceptions\DataReaderException;

use DOMDocument;
use DOMNamedNodeMap;
use DOMNode;
use DOMImplementation;
use Illuminate\Support\Arr;

class XmlReader extends DataReader{
    protected $type = 'xml';

    /**
     * @var DOMDocument
     */
    protected $xml = null;


    /**
     * @inheritDoc
     */
    public function process()
    {
        if($this->sourceType == 'file' || $this->sourceType == 'url'){
            $xmlData = simplexml_load_file($this->source);
        }
        else{
            $xmlData = simplexml_load_string($this->source);
        }
        
        $dom = dom_import_simplexml($xmlData)->ownerDocument;

        $this->makeXml();

        $domData    = $this->createArray($dom);
        $rootData   = array_shift($domData);

        $data = [];
        foreach($rootData as $name=>$info){
            $tmp = ['node'=>$name];
            if(Arr::isAssoc($info)){
                foreach($info as $k=>$value){
                    $tmp[$k] = (is_array($value))?json_encode($value):$value;
                }
                $data[] = $tmp;
            }
            else{
                foreach($info as $subNode){
                    $subTmp = ['node'=>$name];
                    foreach($subNode as $k=>$value){
                        $subTmp[$k] = (is_array($value))?json_encode($value):$value;
                    }
                    $data[] = $subTmp;
                }
            }
        }

        $this->data = $data;
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
     * Convert an XML to Array.
     *
     * @param string|DOMDocument $inputXml
     *
     * @return array
     * @throws Exception
     */
    public function createArray($inputXml)
    {
        if (is_string($inputXml)) {
            try {
                $this->xml->loadXML($inputXml);
                if (!is_object($this->xml) || empty($this->xml->documentElement)) {
                    throw new DataReaderException('FPDO: XmlReader invalid input XML');
                }
            } catch (\Exception $e) {
                throw new DataReaderException('FPDO: XmlReader error while parsing the XML string - '.$e->getMessage());
            }
        } 
        elseif (is_object($inputXml)) {
            if (get_class($inputXml) != 'DOMDocument') {
                throw new DataReaderException('FPDO: XmlReader the input XML object should be of type: DOMDocument');
            }
            $xml = $this->xml = $inputXml;
        } 
        else {
            throw new DataReaderException('FPDO: XmlReader  Invalid input');
        }

        $docType = $this->xml->doctype;
        if ($docType) {
            $array['docType'] = [
                'name'              => $docType->name,
                'entities'          => $this->getNamedNodeMapAsArray($docType->entities),
                'notations'         => $this->getNamedNodeMapAsArray($docType->notations),
                'publicId'          => $docType->publicId,
                'systemId'          => $docType->systemId,
                'internalSubset'    => $docType->internalSubset,
            ];
        }

        $array[$this->xml->documentElement->tagName] = $this->convertXmlToArray($this->xml->documentElement);
        $this->xml = null;    // clear the xml node in the class for 2nd time use.

        return $array;
    }


    /**
     * Convert an XML to an Array.
     *
     * @param DOMNode $node - XML as a string or as an object of DOMDocument
     *
     * @return array
     */
    protected function convertXmlToArray(DOMNode $node)
    {
        $output = [];

        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
            break;

            case XML_ELEMENT_NODE:
                // for each child node, call the covert function recursively
                for ($i = 0, $m = $node->childNodes->length; $i < $m; ++$i) {
                    $child = $node->childNodes->item($i);
                    $value = $this->convertXmlToArray($child);
                    if (isset($child->tagName)) {
                        $tag = $child->tagName;

                        // assume more nodes of same kind are coming
                        if (!array_key_exists($tag, $output)) {
                            $output[$tag] = [];
                        }
                        $output[$tag][] = $value;
                    } else {
                        //check if it is not an empty node
                        if (!empty($value) || $value === '0') {
                            $output = $value;
                        }
                    }
                }

                if (is_array($output)) {
                    // if only one node of its kind, assign it directly instead if array($value);
                    foreach ($output as $tag => $value) {
                        if (is_array($value) && count($value) == 1) {
                            $output[$tag] = $value[0];
                        }
                    }
                    if (empty($output)) {
                        //for empty nodes
                        $output = '';
                    }
                }

                // loop through the attributes and collect them
                if ($node->attributes->length) {
                    $attributes = [];
                    foreach ($node->attributes as $attrName => $attrNode) {
                        $attributes[$attrNode->nodeName] = $attrNode->value;
                    }
                    // if its an leaf node, store the value in nodeValue instead of directly storing it.
                    if (!is_array($output)) {
                        $output = ['nodeValue' => $output];
                    }
                    
                    $nodeAttributesName = config('fpdo.xml_attributes','nodeAttributes');
                    $output[$nodeAttributesName] = $attributes;
                }
                break;
        }

        return $output;
    }


    /**
     * @param DOMNamedNodeMap $namedNodeMap
     * @return array|null
     */
    protected function getNamedNodeMapAsArray(DOMNamedNodeMap $namedNodeMap)
    {
        $result = null;
        if ($namedNodeMap->length) {
            foreach ($namedNodeMap as $key => $entity) {
                $result[$key] = $entity;
            }
        }

        return $result;
    }

}

