<?php

    class logSystem{
        
        var $log_file_path=null; 
        
        public function mlog($msg){
            
            $msg = "[".date('Y-m-d H:i:s')."] ".$msg;
            print($msg."\n");
            
            
            if($this->log_file_path==null)
                $this->log_location ();
                
            $fh=fopen($this->log_file_path, 'a+');
            $fh=  fwrite($fh, $msg.chr(10));
            fclose($fh);
                
        }
        
        public function log_location(){
            
            $filename=__FILE__;
            $default_path=dirname($filename);
            $this->log_file_path=$default_path.'/log';
            
        }
        
    }

?>
