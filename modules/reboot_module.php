<?php        

    class reboot extends logSystem{
        
        var $soap;
        
        function __construct() {
            
            $filename=__FILE__;
            $current_directory=dirname($filename);
            $root_directory=  dirname($current_directory);                        
            
            $xml_string=file_get_contents($root_directory.'/xml_templates/reboot.xml');
            
            if($xml_string===false)
                $this->mlog('Could not open reboot.xml');
            
            $header="HTTP/1.1 200 OK\r\nContent-type: text/xml; charset=ISO-8859-1\r\nAccept: text/xml\r\nContent-length: ".strlen($xml_string)."\r\nCache-Control: no-cache\r\n\r\n";

            $info=$header.$xml_string;
            
            $this->soap=$info;
                        
        }
        
        function reboot_get_soap(){
            return $this->soap;
        }
        
    }
?>
