<?php
    
    $filename=__FILE__;
    $current_directory=dirname($filename);
    $root_directory=  dirname($current_directory);

    require_once '../logSystem.php';
    require_once 'server_conf.php';
    require_once $root_directory.'/xml_parser/xml_parser.php';
     # php socket server
    error_reporting(E_ERROR);
    
    class socket_server extends server_conf{
        
        private $acs_ip;
        private $acs_port;
        private $peer_ip;
        private $peer_port;
        private $acs_functions;
        private $write_obj=array();        
        private $client=array();
        private $acs_functions_init=array();
                
        function __construct()
        {
            #to avoid to override the parent class which is server_conf
            parent::__construct();
            
            $server_conf_obj=new server_conf();
            
            $this->write_obj=$server_conf_obj->server_conf_get_obj();                        
            
            $acs_parameters=$server_conf_obj->server_conf_get_parameters();                        
            
            $this->acs_ip=$acs_parameters['acs_ip'];
            $this->acs_port=$acs_parameters['acs_port'];            
            $this->acs_functions=$acs_parameters['acs_functions'];
            $this->acs_functions_init=$acs_parameters['acs_functions'];
            
            $this->mlog('Server start on socket '.$this->acs_ip.':'.$this->acs_port);
                      
        }
        
        function socket_create_master()
        {
            
            # No timeouts, flush content immediatly
            set_time_limit(0);
            ob_implicit_flush();

            # Create socket
            if(($socket = socket_create(AF_INET,SOCK_STREAM,0))===false )
            {
                $this->mlog ('Could not create socket');
                $this->mlog ('End of script');
                die();
            }                    

            # Bind to socket
            if(socket_bind($socket,$this->acs_ip,$this->acs_port)===false)
            {
                $this->mlog ('Could not bind to socket');
                $this->mlog ('End of script');
                die();
            }

            # Start listening
            if(socket_listen($socket)===false)
            {
                $this->mlog ('Could not set up socket listener');
                $this->mlog ('End of script');
                die();
            }
                                    
            return $socket;
            
        }                
        
        function socket_accept_client($master_socket)
        {
            $peer_ip=null;
            $peer_port=null;            
            
            $this->mlog('Waiting for connections ...');
            
            socket_set_block($master_socket);
            
            if(($client= socket_accept($master_socket))===false)
            {
                $this->mlog('socket_accept() failed: '.socket_strerror($client));
            }
            else
            {
                socket_getpeername($client, $peer_ip, $peer_port);
                $this->peer_ip=$peer_ip;
                $this->peer_port=$peer_port;
                
                $this->mlog('Client '.$peer_ip.' connected');
            }
            
            return $client;
            
        }
        
        function socket_read_data($master_socket, $client)
        {
            # Adding master_socket and the client into array to pass them to secket_select
            $this->client[0]=$client;
            $this->client[1]=$master_socket;
            
            # Tracks if reading loop is over
            $flag=true;
            
            # Stores the whole data received from th cpe
            $data=null;
            
            # Tracks if a socket_close is called
            $connection_state=true;
            
            # Tracks is a session is established
            $session_established=false;
            
            # Tracks the response from the cpe per read loop
            $temp=null;
            
            # Makes a copy of the acs_functios in order to use them for a new connection
            $this->acs_functions=$this->acs_functions_init;
            
            # Counts the commands that are called per session in order to call them one 
            # by one. So each command will need a readind loop to be executed.
            $counter=0;
            
            # Set 5 sec timeout to client socket           
            socket_set_option($client,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));
            
            # Set up a blocking call to socket_select(), waits for a change in socket stastus
            socket_select($this->client,$write = NULL, $except = NULL, $tv_sec= NULL);                  
            
            # Reading loop for a specific session
            do{                                
                
                # Reading data from the client
                $input = socket_read($client,2048);                                                                
                
                # Error output
                if($input===false && $connection_state==true)
                    $this->mlog('Read error '.socket_strerror(socket_last_error())).chr(10);
                
                # The socket has timed out and the connection is closed
                if($input==null)
                {
                                                                                
//                    print_r($data);
                    
                    $this->server_http_extract_parts($data);                                        
                    
                    socket_close($client);
                    
                    unset($client);
                    
                    unset($data);
                    
                    unset($this->client);
                    
                    $flag= false;
                    
                    $this->mlog('Client ip: '.$this->peer_ip.' disconnected');                                                            

                }
                
                # If there are any data from the socket
                if($input)
                {                                        
                    
                    $data.=$input;
                                                            
                    $temp.=$input;
                    
                    # If a session has been estrablished this part of code is executed.
                    if($session_established==true)
                    {
                        $counter++;
                        $this->server_session($client,$counter,$connection_state);
                    }

                    # This means the modem has sent the complete response after the request from the acs.
                    # The acs send an emptyResponse message in order to start a session with the cpe.
                    if(stripos($temp,'</soapenv:Envelope>')!==false && $session_established===false)
                    {   
                        
                        $this->mlog('Calling EmptyReposnse Function');
                        $this->server_session_informResponse($client);
                        
                        $session_established=true;
                        
                        $temp=null;                            
                    }                                        
                    
                }
                
            }while($flag==true);
                               
        }

        private function server_session($client,& $counter,& $connection_state)
        {            
            
            $function=$this->acs_functions[0];
            
            switch ($function):
                case 'GetRPCMethods':                    
                    switch ($counter):
                        case 1:
                            $this->mlog('Calling getRPCMethods Function');
                            $this->server_session_getRPCMethods($client);
                            break;
                        case 2:
                            socket_close($client);
                            unset($this->acs_functions[0]);
                            $connection_state=false;
                            $counter=0;
                        break;
                    endswitch;
                    
                    break;
                    
                case 'Reboot':
                    switch ($counter):
                        case 1:                    
                            $this->mlog('Calling Reboot Function');
                            $this->server_session_reboot($client);
                            break;
                        case 2:
                            $this->mlog('Calling EmptyReposnse Function');
                            $this->server_session_emptyResponse($client);
                            break;
                        case 3:
                            $this->mlog('Calling socket_close() Function');
                            socket_close($client);
                            unset($this->acs_functions[0]);
                            $connection_state=false;
                            $counter=0;
                            break;
                    endswitch;
                            
                    break;
                case 'SetParameterValues':
                    switch ($counter):
                        case 1:
                            $this->mlog('Calling SetParameters Function');
                            $this->server_session_setParameter($client);
                            break;
                        case 2:
                            $this->mlog('Calling EmptyReposnse Function');
                            $this->server_session_emptyResponse($client);
                            break;
                        case 3:
                            $this->mlog('Calling socket_close() Function');
                            socket_close($client);
                            unset($this->acs_functions[0]);
                            $connection_state=false;
                            $counter=0;
                            break;
                    endswitch;
                    
                    break;
            endswitch;
            
        }
   
        private function server_session_informResponse($client)
        {                        
            
            $soap_obj=$this->write_obj['InformResponse'];                                                
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in init_session failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }

        private function server_session_getRPCMethods($client)
        {
            $soap_obj=$this->write_obj['GetRPCMethods'];
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in init_getRPCMethods failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }

        private function server_session_reboot($client)
        {
            $soap_obj=$this->write_obj['Reboot'];
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in Reboot failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }

        private function server_session_setParameter($client)
        {
            $soap_obj=$this->write_obj['SetParameterValues'];                        
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in SetParameterValues failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }

        private function server_session_emptyResponse($client)
        {
            $soap_obj=$this->write_obj['EmptyResponse'];                        
                                    
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in EmptyResponse failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        private function server_http_extract_parts($data)
        {
            $data_array=$this->server_http_dechunk($data);
            
            $soap_header_array=$this->server_http_soap_header($data_array);
            
//            print_r($soap_header_array);
            
            $xml_parser=new xml_parser($this->acs_functions_init,$soap_header_array['soap_array']);
            
            
            

            
//            $this->server_data_parser_xml_parser($soap_header_array['soap_array'], $soap_header_array['header_array']);
        }
                        
        
        
        
        # Function for removing chunk data size information
        private function server_http_dechunk($data)
        {
            $data_array = explode("\r\n", $data);
            
            
            foreach($data_array as $line_num=>$line_string)
            {
                $pattern='/^([0-9A-E]){1,}/i';
                $r=preg_match($pattern, trim($line_string),$match);
                
                # A line with a hex number number has been found
                if($r)
                    unset($data_array[$line_num]);
                                                    
            }
            
            return $data_array;
            
        }               
        
        private function server_http_soap_header($data_array)
        {
            $soap_start_flag=false;
            $soap_end_flag=false;
            $header_start_flag=false;
            $header_end_flag=false;
            $soap_array=array();
            $header_array=array();
            $counter=0;                        
            
            foreach($data_array as $line_num=>$line_string)
            {
                
                if(stripos(trim($line_string),'<soapenv:Envelope')!==false && $soap_start_flag===false)
                {                 
                    $soap_start_flag=true;
                    $soap_end_flag=false;
                }
                
                if($soap_start_flag==true && $soap_end_flag==false)
                {                    
                    $soap_array[$counter].=$line_string.chr(10);
                    unset($data_array[$line_num]);
                }                
                
                if(stripos(trim($line_string), '</soapenv:Envelope>')!==false && $soap_end_flag===false)
                {                   
                    $soap_end_flag=true;
                    $soap_start_flag=false;
                    $counter++;
                }
                
            }
            
            $counter=0;
            
            foreach($data_array as $line_num=>$line_string)
            {
                if(stripos(trim($line_string),'POST')!==false && $header_start_flag===false)
                {                 
                    $header_start_flag=true;
                    $header_end_flag=false;
                }
                
                if($header_start_flag==true && $header_end_flag==false)
                {                    
                    $header_array[$counter].=$line_string.chr(10);
                    unset($data_array[$line_num]);
                } 
                
                if(trim($line_string)=='' && $header_end_flag===false)
                {                   
                    $header_end_flag=true;
                    $header_start_flag=false;
                    $counter++;
                }
            }
                                    
            return array('soap_array'=>$soap_array,'header_array'=>$header_array);
        }
        
        private function is_hex($hex) 
        {     
            $hex = strtolower(trim(ltrim($hex,"0")));
            if (empty($hex)) 
            { 
                $hex = 0; 
                
            }
            $dec = hexdec($hex);
            
            return ($hex == dechex($dec));
        } 
        
    }
    
?>
