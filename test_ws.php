<?php
if (isset($_GET['js'])) {
    ?>
    <!DOCTYPE html>
    <html>
	<head>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>    
	</head>
	<body>
	<div style="font-size: 10em" id="root"></div>

	<br><a href="#" id="connect">connect</a><br>
	<br><a href="#" id="send">send</a><br>
	<br><a href="#" id="ask">ask</a><br>
	<br><a href="#" id="close">close</a><br>
        <script>
            function get_ws(message_handler)
            {
                var websock={};
                websock.open=false;
                websock.host= 'ws://0.0.0.0:12345/websockets.php';
                console.log('Opening ws connection');
                websock.socket = new WebSocket(websock.host);

                websock.socket.onmessage = function(e) {
                    console.log('Got a message');
                    message_handler(e.data);
                };
                websock.socket.onerror=function(e) {
                    websock.open=false;
                    console.log('An error happened');
                    //setTimeout(function () {
                    //    console.log('In timeout');
                    //    get_ws(message_handler);
                    //},1000);
                }
                websock.socket.onopen=function(e) {
                    websock.open=true;
                    console.log('Opened');
                }
                websock.socket.onclose=function(e) {
                    websock.open=false;;
                    console.log('Closed');
                    // setTimeout(get_ws(message_handler),10000);
                }
                websock.send=function(msg) {
                    if (websock.open) {
                        console.log('Sending '+msg);
                        websock.socket.send(msg);
                    } else {
                        console.log('Cant send '+msg);
                    }
                }
                websock.close=function() {
                    websock.socket.close();
                }
                return websock;
            }

		var ws={};
	    $('#connect').on('click',function () {
		    console.log('connect clicked');
		    ws=get_ws(function(m) {
			console.log('Got:'+m);
			document.getElementById('root').innerHTML = m;
		    });
	    });
	    $('#send').on('click',function () {
		    console.log('send clicked');
			ws.send('Hello from js');
	    });
	    $('#ask').on('click',function () {
		    console.log('ask clicked');
			ws.send('ask from js');
	    });
	    $('#close').on('click',function () {
		    console.log('close clicked');
		    ws.close();
	    });


        </script>
    </body>
    </html>
    <?php
    exit;
}

function perm_error()
{
    $e=socket_last_error();
    echo "$e ".socket_strerror($e)."\n";
    if ($e==11) {
        return false;
    }
    return true;
}


function ws_read($sp, $wait_for_end=true, &$err='')
{
    $out_buffer="";
    do {
        // Read header
        $header=socket_read($sp, 2);
        if ($header===false) {
            if (perm_error()) {
                return false;
            } else {
                return "";
            }
        }
        $opcode = ord($header[0]) & 0x0F;
        $final = ord($header[0]) & 0x80;
        $masked = ord($header[1]) & 0x80;
        $payload_len = ord($header[1]) & 0x7F;

        // Check for close opcode
        if ($opcode==8) {
            return false;
        }
  
        // Get payload length extensions
        $ext_len = 0;
        if ($payload_len >= 0x7E) {
            $ext_len = 2;
            if ($payload_len == 0x7F) {
                $ext_len = 8;
            }
            $ext=socket_read($sp, $ext_len);
            if ($ext===false) {
                if (perm_error()) {
                    return false;
                } else {
                    return "";
                }
            }
  
            // Set extented paylod length
            $payload_len= 0;
            for ($i=0;$i<$ext_len;$i++) {
                $payload_len += ord($header[$i]) << ($ext_len-$i-1)*8;
            }
        }
  
        // Get Mask key
        if ($masked) {
            $mask=socket_read($sp, 4);
            if ($mask===false) {
                if (perm_error()) {
                    return false;
                } else {
                    return "";
                }
            }
        }
  
        // Get payload
        $frame_data='';
        do {
            $frame= socket_read($sp, $payload_len);
            if ($frame===false) {
                if (perm_error()) {
                    return false;
                } else {
                    return "";
                }
            } // die("Reading from websocket failed.");
            $payload_len -= strlen($frame);
            $frame_data.=$frame;
        } while ($payload_len>0);
  
        // if opcode ping, reuse headers to send a pong and continue to read
        if ($opcode==9) {
            // Assamble header: FINal 0x80 | Opcode 0x02
        $header[0]=chr(($final?0x80:0) | 0x0A); // 0x0A Pong
        socket_write($sp, $header.$ext.$mask.$frame_data);
  
        // Recieve and unmask data
        } elseif ($opcode<3) {
            $data="";
            $data_len=strlen($frame_data);
            if ($masked) {
                for ($i = 0; $i < $data_len; $i++) {
                    $data.= $frame_data[$i] ^ $mask[$i % 4];
                }
            } else {
                $data.= $frame_data;
            }
            $out_buffer.=$data;
        }
  
        // wait for Final
    } while ($wait_for_end && !$final);
  
    return $out_buffer;
}




function change_to_websocket($client)
{
    $request = socket_read($client, 5000);
    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
    $key = base64_encode(pack(
        'H*',
        sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
    ));
    $headers = "HTTP/1.1 101 Switching Protocols\r\n";
    $headers .= "Upgrade: websocket\r\n";
    $headers .= "Connection: Upgrade\r\n";
    $headers .= "Sec-WebSocket-Version: 13\r\n";
    $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
    socket_write($client, $headers, strlen($headers));
    socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>100));
};


function ws_write($client, $msg)
{
    $a = str_split($msg, 125);
    if (count($a) == 1) {
        $data= "\x81" . chr(strlen($a[0])) . $a[0];
    } else {
        $data = "";
        foreach ($a as $o) {
            $data .= "\x81" . chr(strlen($o)) . $o;
        }
    }

    socket_write($client, $data, strlen($data));
}



$address = '0.0.0.0';
$port = 12345;
$server=socket_create_listen($port);
socket_set_nonblock($server);
$connected=false;
while (true) {
    echo "\nStart loop\n";
    if (!$connected) {
        echo "Not connected ";
        // Accept
        $client = socket_accept($server);
        if (!empty($client)) {
            echo "Accepted ";
            change_to_websocket($client);
            echo "Changed to WS ";
            $connected=true;
        }
    }

    if ($connected) {
        echo "Connected\n";
        $read=ws_read($client);
        if ($read===false) {
            echo "Got false from open socket ";
            $connected=false;
        } else {
            if (!empty($read)) {
                if ($read=='ask from js') {
                    echo "Got $read, sending reply ";
                    $got_back=ws_write($client, "reply from php ".rand(100,999));
                    echo "Sent reply and got back $got_back\n";
                } else {
                    echo "Do something with $read ";
                }
            } else {
                echo "Got nothing from read ";
            }
        }
        //if (rand(1, 2)==1) {
        //    echo "Writing something ";
        //    ws_write($client, 'Some message '.rand(100, 999));
        //}
    }
    echo "Pause...";
    sleep(1);
}
