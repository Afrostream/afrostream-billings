<?php

function db_connect() {
    
    static $connection;

    if(!isset($connection)) {
        
        $config = parse_ini_file('config.ini');
        $connection = new mysqli($config['adress'], $config['username'],$config['password'],$config['dbname']);
    }
    

    return $connection;
}


function db_query($query) {

    $connection = db_connect();

    return $connection->query($query);
}

?>