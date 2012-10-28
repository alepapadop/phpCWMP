<?php
    
    $filename=__FILE__;
    $current_directory=dirname($filename);
    $root_directory=  dirname($current_directory);
    
    require_once '../logSystem.php';
    require_once $root_directory.'/modules/reboot_module.php';
    require_once $root_directory.'/modules/getRPCMethods_module.php';
    require_once $root_directory.'/modules/informResponse_module.php';
    require_once $root_directory.'/modules/setParameterValues_module.php';
    require_once $root_directory.'/modules/emptyResponse_module.php';
    
    class server_conf extends logSystem{
        
        var $options=array('GetParameterNames',
                            'GetParameterValues',
                            'GetParameterAttributes',
                            'SetParameterValues',
                            'SetParameterAttributes',
                            'AddObject',
                            'DeleteObject',
                            'Download',
                            'ScheduleInform',
                            'Reboot',
                            'FactoryReset',
                            'GetRPCMethods',
                            'InformResponse',
                            'EmptyResponse');
        
        var $obj_array=array();
        var $parameter_array=array();
        
        function __construct() {
            
            $this->server_conf_validate_xml_inputs();
            
        }
        
        public function server_conf_get_obj()
        {
            return $this->obj_array;
        }
        
        public function server_conf_get_parameters()
        {
            return $this->parameter_array;
        }
        
        private  function server_conf_validate_xml_inputs()
        {
            $xml_array=array();
            
            $xml_array=$this->server_conf_parse_xml();
            
            foreach($xml_array['acs_functions'] as $functions)
            {                
                $functions=trim($functions);
                
                if(!in_array($functions, $this->options))
                {
                    $this->mlog('Then server_conf.xml file is not valid');
                    die();
                }                
            }
                        
            $this->server_conf_create_objs();
            
        }
        
        private function server_conf_create_objs()
        {
            
            $function_array=$this->server_conf_parse_xml();
            
            $this->parameter_array['acs_ip']=$function_array['acs_ip'];
            $this->parameter_array['acs_port']=$function_array['acs_port'];
            $this->parameter_array['acs_functions']=$function_array['acs_functions'];
            
            foreach($function_array['acs_functions'] as $function)
            {
                switch ($function):
                    case 'GetRPCMethods':
                        $getRPCMethodsObj=new getRPCMethods();
                        $this->obj_array['GetRPCMethods']=$getRPCMethodsObj;
                        break;
                    case 'Reboot':
                        $rebootObj=new reboot();
                        $this->obj_array['Reboot']=$rebootObj;
                        break;
                    case 'InformResponse':
                        $informResponseObj=new informResponse();
                        $this->obj_array['InformResponse']=new $informResponseObj;
                        break;
                    case 'SetParameterValues':
                        $setParameterValuesObj=new setParameterValues();
                        $this->obj_array['SetParameterValues']=new $setParameterValuesObj;
                        break;
                    case 'EmptyResponse':
                        $emptyResponseObj=new emptyResponse();
                        $this->obj_array['EmptyResponse']=new $emptyResponseObj;
                        break;
                endswitch;
            }
                                    
        }
        
        private function server_conf_parse_xml()
        {
            
            $xml_elements=array();                                        
            
            $server_xml = simplexml_load_file('server_conf.xml');
              
            $xml_elements['acs_ip']=(string)$server_xml->acs_ip;
            $xml_elements['acs_port']=(string)$server_xml->acs_port;
            $functions=(Array)$server_xml->acs_functions;
                                    
            foreach($functions as $function)
            {
                $xml_elements['acs_functions']=$function;
            }                        
            
            return $xml_elements;

        }
        
    }

?>
