<?php        
    
    error_reporting(E_ERROR);
    
    class xml_parser_getRPCMethods_module extends logSystem{

        function xml_parser($xml_string)
        {                        
            
            $filename=__FILE__;
            $current_directory=dirname($filename);
            $root_directory=  dirname($current_directory);
            
            $output='';           

            #create a xml handler to use it for parsing
            $xml_handler=  $this->create_xml_handler($xml_string);           

            #get the device elemet
            $event_object=$this->get_device_getRPCMethodsResponseid($xml_handler);
            #parse the device element
            $output.=$this->parse_device_getRPCMethodsResponseid($event_object);
            #unset variable
            unset($event_object);                        

            #write the data to a human readable file
            $fh=fopen($root_directory.'/parsed_cpe_responses/getRPCMethods_id_'.$id.'.yaml','w+');
            fwrite($fh, $output);
            fclose($fh);

        }

        private function create_xml_handler($xmlStr){


            # create simpleXML object
            $xml = simplexml_load_string($xmlStr);

            # get namespaces of the xml
            $xml->registerXPathNamespace('cwmp', 'urn:dslforum-org:cwmp-1-0');

            #return xml object
            return $xml;
        }       

        private function get_device_getRPCMethodsResponseid($xml){

            $res = $xml->xpath('//cwmp:GetRPCMethodsResponse');

            return $res;

        }

        private function parse_device_getRPCMethodsResponseid($event_object){

            $output='';

            foreach($event_object as $object)
            {
                foreach($object as $eventStruct)
                {
                    foreach($eventStruct as $key=>$value)
                        $output.='RPC function : '.$value.chr(10);
                }

            }

            return $output;

        }

        
    }
?>
