<?php        

    class sessionClose extends logSystem{
        
        var $soap;
        
        function __construct() {
                             
            
            
            $header="HTTP/1.1 200 OK \r\n\ Connection: close \r\n Content-Length: 0 \r\n\r\n";

            $info=$header;
            
            $this->soap=$info;
                        
        }
        
        function sessionClose_get_soap(){
            return $this->soap;
        }
        
    }
?>
