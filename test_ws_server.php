<?php

require_once('websocket.php');


$handle=ws_create('12345');

while (true) {
    $read=ws_poll($handle);
    if ($read!='') {
        if ($read=='ask from js') {
            echo "Got $read, sending reply ";
            $got_back=ws_write($handle, "reply from php ".rand(100, 999));
            echo "Sent reply and got back $got_back\n";
        } else {
            echo "Do something with $read ";
        }
    }
    usleep(2000);
}
