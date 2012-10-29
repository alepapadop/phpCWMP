<?php         
    
    require_once 'server.php';
    require_once 'server_conf.php';        
        
     # php socket server
    error_reporting(E_ERROR);
                             
    $server=new socket_server();
    
    $master_socket=$server->socket_create_master();        
    
    while(true)
    {
                        
        $client=$server->socket_accept_client($master_socket);                
        
        $server->socket_read_data($master_socket, $client);
                
        socket_close($client);
        
        unset($client);
        
        sleep(1);
                        
    }    

?>
