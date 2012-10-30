<?php        

    $filename=__FILE__;
    $current_directory=dirname($filename);
    $root_directory=  dirname($current_directory);

    require_once '../logSystem.php';
    require_once 'server_conf.php';
    require_once $root_directory.'/xml_parser/xml_parser.php';
    
    error_reporting(E_ERROR);
    
    class socket_server extends server_conf{
        
        private $acs_ip;
        private $acs_port;
        private $cpe_ip;
        private $cpe_port;
        private $cpe_auth;
        private $cpe_user;
        private $cpe_pass;
        private $peer_ip;
        private $peer_port;
        
        # Stores the acs_functions that are defined in the server_conf_xml
        # This array is always edited during the run of the script, thats because
        # I keep a copy of it in acs_functions_init array
        private $acs_functions;
        
        # Stores obj from the *_module classes
        private $write_obj=array();
        
        # Stores the master_socket and the client
        private $client=array();
        
        # Stores the original copy of the acs_functions defined in server_conf_xml
        private $acs_functions_init=array();
                
        function __construct()
        {
            # Avoid to override the parent class which is server_conf
            parent::__construct();
            
            $server_conf_obj=new server_conf();
            
            # Get the obj that were created in server_conf class
            $this->write_obj=$server_conf_obj->server_conf_get_obj();                        
            
            # Get the parameters that were defined in server_conf.xml
            $acs_parameters=$server_conf_obj->server_conf_get_parameters();                        
            
            $this->acs_ip=$acs_parameters['acs_ip'];
            $this->acs_port=$acs_parameters['acs_port'];
            $this->cpe_ip=$acs_parameters['cpe_ip'];
            $this->cpe_port=$acs_parameters['cpe_port'];
            $this->cpe_auth=$acs_parameters['cpe_auth'];
            $this->cpe_user=$acs_parameters['cpe_user'];
            $this->cpe_pass=$acs_parameters['cpe_pass'];
            $this->acs_functions=$acs_parameters['acs_functions'];
            $this->acs_functions_init=$acs_parameters['acs_functions'];
            
            $this->mlog('Server start on socket '.$this->acs_ip.':'.$this->acs_port);
                      
        }
        
        # Creates the master socket
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
        
        # Accepts a client
        function socket_accept_client($master_socket)
        {
            $peer_ip=null;
            $peer_port=null;            
            
            $this->mlog('Waiting for connections ...');
            
            # Waits the user to push enter in order to send aconnection request message
            $this->server_trigger_cpe();
            
            # Set blocking state under the client connects to master_socket
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
        
        # Reading loop for the received data from the CPE
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
                    
                    # Extract soap response, headers and chunks from the received data
                    $this->server_http_extract_parts($data);                                        
                    
                    socket_close($client);
                    
                    unset($client);
                    
                    unset($data);
                    
                    unset($this->client);
                    
                    # Change the flag status to exit the do ... while loop
                    $flag= false;
                    
                    $this->mlog('Client ip: '.$this->peer_ip.' disconnected');                                                            

                }
                
                # If there are any data from the socket
                if($input)
                {                                        
                    # Stores all the data received from the CPE
                    $data.=$input;
                    
                    # Used as a temp variable for pattern recognision purposes
                    $temp.=$input;
                    
                    # If a session has been estrablished this part of code is executed.
                    if($session_established==true)
                    {
                        # Counter for witch command should be executed nexr
                        $counter++;
                        
                        # Function with the commands that must be executed
                        $this->server_session($client,$counter,$connection_state);
                    }

                    # This means the modem has sent the complete response after the request from the acs.
                    # The acs send an emptyResponse message in order to start a session with the cpe.
                    if(stripos($temp,'</soapenv:Envelope>')!==false && $session_established===false)
                    {   
                        
                        $this->mlog('Calling EmptyReposnse Function');
                        
                        # Acs sends the inform Response to the CPE to start session
                        $this->server_session_informResponse($client);
                        
                        # Flag variable to infrom that session has been established
                        $session_established=true;
                        
                        $temp=null;                            
                    }                                        
                    
                }
                
            }while($flag==true);
                               
        }
        
        # Sends the connect request to the CPE with the credentials
        # If there are any credentials required the functions sends two time
        # curl connect request because in the first atemp to connect the modem
        # retrun a "401 authentication" info header and then is waiting for the
        # credentials to be send.
        private function server_send_cpe_credentials()
        {
            
            $url='http://'.$this->cpe_ip.':'.$this->cpe_port;
            $user=$this->cpe_user;
            $pass=$this->cpe_pass;
            $auth=$this->cpe_auth;
            
            
            $headers = array(             
                "Content-type: text/xml;charset=\"utf-8\"", 
                "Accept: text/xml",

            );

            $curl_session=curl_init();
            curl_setopt($curl_session, CURLOPT_URL,            $url );   
            curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 1); 
            curl_setopt($curl_session, CURLOPT_TIMEOUT,        1);
            curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, false);  
            curl_setopt($curl_session, CURLOPT_SSL_VERIFYHOST, false);                 
            curl_setopt($curl_session, CURLOPT_HTTPHEADER    , $headers);
            curl_setopt($curl_session, CURLINFO_HEADER_OUT   , true);
            
            if(strtolower($auth)=='digest')
            {
                curl_setopt($curl_session, CURLOPT_HTTPAUTH      , CURLAUTH_DIGEST);
                curl_setopt($curl_session, CURLOPT_USERPWD,        $user . ":" . $pass);
                $r = curl_exec($curl_session);
            }
            elseif(strtolower($auth)=='basic')
            {
                curl_setopt($curl_session, CURLOPT_HTTPAUTH      , CURLAUTH_BASIC);
                curl_setopt($curl_session, CURLOPT_USERPWD,        $user . ":" . $pass);
                $r = curl_exec($curl_session);
            }

            $r = curl_exec($curl_session);                        
            
            if($r===false)
                $this->mlog ('CPE trigger failed');                        

            curl_close($curl_session);
                        
        }
        
        # Waits the user to push ENTER in order to send the connection request
        # to the CPE
        private function server_trigger_cpe()
        {
            $this->mlog('Press enter to trigger the CPE');
            
            # Open file pointer to read from stdin
            $fr=fopen("php://stdin","r");
            
            # Read a maximum of 128 characters
            $input = fgets($fr,128);
    
            fclose ($fr);
            
            # Send the connection request to the CPE
            $this->server_send_cpe_credentials();                        
        }
        
        # For each acs_function defined in server_conf.xml there are specific
        # steps (socket_writes) that must be done with specific order. After
        # each step (socket_write) the code exits from server_session in order 
        # to read the data in the reading loop. Some data are passed by reference
        # and not by value.
        private function server_session($client,& $counter,& $connection_state)
        {            
            # Check always the first function in array because after each 
            # function is executed the function is beign unset from the array.
            
            $function=$this->acs_functions[0];
            
            switch ($function):
                case 'GetRPCMethods':                    
                    switch ($counter):
                        case 1:
                            $this->mlog('Calling getRPCMethods Function');
                            $this->server_session_getRPCMethods($client);
                            break;
                        case 2:
                            $this->mlog('Calling sessionClose Function');
                            $this->server_session_sessionClose($client);
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
                            $this->mlog('Calling sessionClose Function');
                            $this->server_session_sessionClose($client);
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
                            $this->mlog('Calling sessionClose Function');
                            $this->server_session_sessionClose($client);
                            socket_close($client);
                            unset($this->acs_functions[0]);
                            $connection_state=false;
                            $counter=0;
                            break;
                    endswitch;
                    
                    break;
            endswitch;
            
        }
        
        # Sends to the CPE a connection close request.
        # This is the first message that must be send to the CPE to trigger it
        # and the CPE sends an Inform message
        private function server_session_sessionClose($client)
        {
            $soap_obj=$this->write_obj['SessionClose'];
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in session_close failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Sends to the CPE a informResponse message
        # After the CPE has send the Inform message a informReponse must be send
        private function server_session_informResponse($client)
        {                        
            
            $soap_obj=$this->write_obj['InformResponse'];                                                
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in init_session failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Sends to the CPE a getRPCMethod message
        private function server_session_getRPCMethods($client)
        {
            $soap_obj=$this->write_obj['GetRPCMethods'];
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in init_getRPCMethods failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Sends to the CPE a Reboot message
        private function server_session_reboot($client)
        {
            $soap_obj=$this->write_obj['Reboot'];
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in Reboot failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Sends to the CPE a setParameter message
        private function server_session_setParameter($client)
        {
            $soap_obj=$this->write_obj['SetParameterValues'];                        
            
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in SetParameterValues failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Sends to the CPE a emptyReaponse message
        # After a session establishment the acs server must send a emptyResponse
        # message to the cpe, as a ACK mechanism
        private function server_session_emptyResponse($client)
        {
            $soap_obj=$this->write_obj['EmptyResponse'];                        
                                    
            $r=socket_write($client,$soap_obj->soap);
            
            if(!$r)
                $this->mlog('socket_write in EmptyResponse failed for peer '.$this->peer_ip.' '.socket_strerror($client));
            
            sleep(1);
        }
        
        # Creates the soap_header_array which contains the header and soap data
        # that the CPE has send to the acs server and parses thse data.
        private function server_http_extract_parts($data)
        {
            $data_array=$this->server_http_dechunk($data);
            
            $soap_header_array=$this->server_http_soap_header($data_array);            
            
            # Calls the parser
            $xml_parser=new xml_parser($this->acs_functions_init,$soap_header_array['soap_array']);
                                               
        }                                        
        
        # Removes the chunk information from the data that the CEP sends back 
        # to the server
        private function server_http_dechunk($data)
        {
            # Splits the data from the CPE in array
            $data_array = explode("\r\n", $data);
            
            # Check its array entry if there is a line which contains only
            # hex charactes and removes this array entry.
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
        
        # Creates an array  with the soap and header data. Gets as input the 
        # output from the server_http_dechunk function.
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
                # Finds when a soap message starts
                if(stripos(trim($line_string),'<soapenv:Envelope')!==false && $soap_start_flag===false)
                {                 
                    $soap_start_flag=true;
                    $soap_end_flag=false;
                }
                
                # Records a soap message
                if($soap_start_flag==true && $soap_end_flag==false)
                {                    
                    $soap_array[$counter].=$line_string.chr(10);
                    unset($data_array[$line_num]);
                }                
                
                # Finds when a soap message ends
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
                # Finds when a header message starts
                if(stripos(trim($line_string),'POST')!==false && $header_start_flag===false)
                {                 
                    $header_start_flag=true;
                    $header_end_flag=false;
                }
                
                # Records the header message
                if($header_start_flag==true && $header_end_flag==false)
                {                    
                    $header_array[$counter].=$line_string.chr(10);
                    unset($data_array[$line_num]);
                } 
                
                #Finds when a header message ends
                if(trim($line_string)=='' && $header_end_flag===false)
                {                   
                    $header_end_flag=true;
                    $header_start_flag=false;
                    $counter++;
                }
            }
                                    
            return array('soap_array'=>$soap_array,'header_array'=>$header_array);
        }
                        
    }
    
?>
