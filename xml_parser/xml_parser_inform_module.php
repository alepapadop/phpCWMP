<?php


    error_reporting(E_ERROR);
    libxml_use_internal_errors(true);
    
    class xml_parser_inform_module extends logSystem{

        function xml_parser($xml_string)
        {
            
            
            $filename=__FILE__;
            $current_directory=dirname($filename);
            $root_directory=  dirname($current_directory);
            
            $output='';                                                           
            
            #create a xml handler to use it for parsing
            $xml_handler=  $this->create_xml_handler($xml_string);

            
            
            #get the id element
            $id_object=$this->get_device_id($xml_handler);            
            #parse the id element
            $output.=$this->parse_device_id($id_object);           
            #unset variable
            unset($id_object);

            
            
            #get the device elemet
            $event_object=$this->get_device_event($xml_handler);
            #parse the device element
            $output.=$this->parse_device_event($event_object);
            #unset variable
            unset($event_object);


            #get the time element
            $time_object=$this->get_current_time($xml_handler);
            #parse the time element
            $output.=$this->parse_current_time($time_object);
            #unset variable
            unset($time_object);


            #get the parameter list
            $parameter_object=$this->get_parameterList($xml_handler);
            #parse the parameter list element
            $output.=$this->parse_parameterList($parameter_object);
            #unset variable
            unset($parameter_object);                                              
            
            #write the data to a human readable file
            $fh=fopen($root_directory.'/parsed_cpe_responses/Inform_id.yaml','w+');
            fwrite($fh, $output);
            fclose($fh);

        }

        private function create_xml_handler($xmlStr){           
            
            # create simpleXML object            
            $xml = simplexml_load_string($xmlStr);
            
            if($xml===false)
                $this->mlog ('Could not create xml obj');                                                
            
            # get namespaces of the xml
            $xml->registerXPathNamespace('cwmp', 'urn:dslforum-org:cwmp-1-0');
            
            
            #return xml object
            return $xml;
        }

        private function get_device_id($xml){        

            $res = $xml->xpath('//cwmp:Inform/DeviceId');                        
            
            return $res;

        }

        private function parse_device_id($id_object){

            $output='';

            foreach($id_object as $object)
            {
                foreach($object as $key=>$value)
                    $output.=$key.' : '.$value.chr(10);
            }

            return $output;
        }

        private function get_device_event($xml){

            $res = $xml->xpath('//cwmp:Inform/Event');

            return $res;

        }

        private function parse_device_event($event_object){

            $output='';

            foreach($event_object as $object)
            {
                foreach($object as $eventStruct)
                {
                    foreach($eventStruct as $key=>$value)
                        $output.=$key.' : '.$value.chr(10);
                }

            }

            return $output;

        }

        private function get_current_time($xml){

            $res = $xml->xpath('//cwmp:Inform/CurrentTime');

            return $res;
        }

        private function parse_current_time($time_object){

            $output='';

            foreach($time_object as $key=>$value)
            {
                $output.='Time : '.$value.chr(10);
            }

            return $output;

        }

        private function get_parameterList($xml){

            $res = $xml->xpath('//cwmp:Inform/ParameterList');

            return $res;

        }

        private function parse_parameterList($parameter_object){

            //print_r($parameter_object);
            $output='';

            foreach($parameter_object as $object)
            {
                foreach($object as $parameterValueStruct)
                {               
                    $paramValue='';
                    foreach($parameterValueStruct as $value)
                    {                                                   

                        $paramValue.=$value.' : ';
                    }
                    $output.=substr($paramValue,0,-3);
                    $output.=chr(10);
                }  
            }

            return $output;
        }
    }
?>
