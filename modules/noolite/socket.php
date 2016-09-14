<?php

    if ($argv[1] == '') {
     exit;
    }

    $total=count($argv);
    for($i=1;$i<$total;$i++) {
     $api_command.=$argv[$i].' ';
    }

    $socket_path="/tmp/nooliterxd.sock";
    $snd_msg = $api_command;
    $rcv_msg = "";
    $timeout = 10;
    $socket = stream_socket_client('unix://'.$socket_path, 
                                    $errorno, $errorstr, $timeout);
    stream_set_timeout($socket, $timeout);

    echo("Sending Message...\n");
    if(!fwrite($socket, $snd_msg))
            echo("Error while writing!!!\n");

    echo("done\n");

?>