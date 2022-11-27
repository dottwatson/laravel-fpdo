<?php 
namespace Fpdo;

use Fpdo\Exceptions\ResourceException;
use Fpdo\Reader\XmlReader;

/**
 * Class to handle files, urls, objects or resources
 */
class Resource{
    
    /**
     * try to get the source type 
     *
     * @param mixed $source
     * @return string
     */
    public static function getType($source)
    {
        if(is_callable($source)){
            return  'callback';
        }
        elseif(is_resource($source) && get_resource_type($source) == 'stream'){
            return 'stream';
        }
        elseif(is_object($source) && is_a($source,'SimpleXMLElement',false)){
            return 'xml';
        }
        elseif(is_object($source)){
            return 'object';
        }
        elseif(is_scalar($source)){
            if(is_file($source) && is_readable($source)){
                return 'file';
            }
            if(filter_var($source,FILTER_VALIDATE_URL)){
                $urlInfo = parse_url($source,PHP_URL_SCHEME);
                switch(strtolower($urlInfo)){
                    case 'http':
                    case 'https':
                    case 'ftp':
                        return 'url';
                    break;
                    default:
                        return null;
                    break;
                }            
            }
            
            return 'string';
        }
        elseif(is_array($source)){
            return 'array';
        }
    }
    
    
    /**
     * parse given source and try to return data
     *
     * @param mixed $source
     * @return mixed
     */
     public static function parse($source){
        if(is_callable($source)){
            return  call_user_func($source);
        }
        elseif(is_resource($source) && get_resource_type($source) == 'stream'){
            return stream_get_contents($source);
        }
        elseif(is_object($source) && is_a($source,'SimpleXMLElement',false)){
            return $source->asXML();
        }
        elseif(is_object($source)){
            return json_encode($source);
        }
        elseif(is_scalar($source)){
            if(is_file($source) && is_readable($source)){
                return file_get_contents($source);
            }
            if(filter_var($source,FILTER_VALIDATE_URL)){
                $urlInfo = parse_url($source,PHP_URL_SCHEME);
                switch(strtolower($urlInfo)){
                    case 'http':
                    case 'https':
                    case 'ftp':
                        return file_get_contents(
                            $source,
                            false,
                            stream_context_create([
                                "ssl"=>[
                                    "verify_peer"=>false,
                                    "verify_peer_name"=>false,
                                ]
                            ])
                        );
                    break;
                    default:
                        return $source;
                    break;
                }            
            }
            
            return (string)$source;
        }
        elseif(is_array($source)){
            return $source;
        }
    } 


    /**
     * Acquire the primitive resource and covert it into parsable data
     *
     * @param mixed $source
     * @return mixed
     */
    public static function acquire($source){
        $type = self::getType($source);
        
        if(!$type){
            throw new ResourceException("invalid resource type for {$source}");
        }
        
        $contents = self::parse($source);
        if($type == 'xml'){
            return XmlReader::createArray($contents);
        }
        
        return $contents;
    }
}
