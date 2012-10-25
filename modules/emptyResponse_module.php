<?php        

    class emptyResponse extends logSystem{
        
        var $soap;
        
        function __construct() {
                                                
            
            $header="HTTP/1.1 200 OK\r\nContent-type: text/xml; charset=ISO-8859-1\r\nAccept: text/xml\r\nContent-length: 0\r\nCache-Control: no-cache\r\n\r\n";

            $info=$header;
            
            $this->soap=$info;
                        
        }
        
        function emptyResponse_get_soap(){
            return $this->soap;
        }
        
    }
?>
