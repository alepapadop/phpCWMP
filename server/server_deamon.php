<?php         
    
    require_once 'server.php';
    require_once 'server_conf.php';
    
    error_reporting(E_ERROR);
    
    # Create a socket server instanse
    $server=new socket_server();
    
    # Create master_socket, the socket for the acs_server
    $master_socket=$server->socket_create_master();        
    
    # Acs server endless loop
    while(true)
    {
        
        # master_socket blocks till the client to connect
        $client=$server->socket_accept_client($master_socket);                
        
        # Reading and sending data to the client
        $server->socket_read_data($master_socket, $client);
        
        # Close client socket after communication termination
        socket_close($client);
        
        unset($client);
        
        sleep(1);
                        
    }    

?>
