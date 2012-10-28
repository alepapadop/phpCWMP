<?php
    
    $filename=__FILE__;
    $current_directory=dirname($filename);
    $root_directory=  dirname($current_directory);

    require_once '../logSystem.php';
    require_once $root_directory.'/xml_parser/xml_parser_getRCPMethods_module.php';
    require_once $root_directory.'/xml_parser/xml_parser_inform_module.php';

    error_reporting(E_ERROR);
    
    class xml_parser extends logSystem
    {
                        
        function __construct($acs_functions_array,$soap_array)
        {
            
            print_r($acs_functions_array);
            
            foreach($acs_functions_array as $function)
            {
                $num=null;
                
                switch ($function):
                    case 'InformResponse':
                        foreach($soap_array as $key=>$soap)
                        {
                            if(stripos($soap,'<cwmp:Inform>'))
                            {
                                $num=$key;
                                break;
                            }
                        }                                                                                                
                        
                        $this->mlog('Parsing inform soap response');
                        $getRPCMethodsXMLObj=new xml_parser_inform_module();
                        $getRPCMethodsXMLObj->xml_parser($soap_array[$num]);
                        break;                    
                    case 'GetRPCMethodsaaa':
                        foreach($soap_array as $key=>$soap)
                        {
                            if(stripos($soap,'<cwmp:GetRPCMethodsResponse>'))
                            {
                                $num=$key;
                                break;
                            }
                        }
                        $this->mlog('Parsing getRPCMEthod soap response');
                                                
                        $informResponseXMLObj=new xml_parser_getRPCMethods_module();
                        $informResponseXMLObj->xml_parser($soap_array[$num]);
                        break;                    
                endswitch;
            }
            
        }                
        
    }

?>
