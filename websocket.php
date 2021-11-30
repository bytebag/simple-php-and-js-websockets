<?php


/**
 * Is the last socket error likely to mean the socket has closed.
 *
 * WARNING the hard coded value(s) here might be OS specific.
 * If you are getting sockets left open on the server when the client has closed,
 * or the server closing the socket when it should not, disable the temp errors here,
 * enable debugging and see what the codes are when the client disconnects ($e variable).
 * Then add code to detect the OS and select the right codes. 
 * The 11 is correct on:
 * Linux 4.19.0-17-amd64 #1 SMP Debian 4.19.194-2 (2021-06-21) x86_64 GNU/Linux
 * 
 * @param [type] $handle
 * @return void
 */
function perm_error($handle)
{
    $e = socket_last_error();
    if ($handle['debug']) {
        echo "$e " . socket_strerror($e) . "\n";
    }

    // Temp error(s)
    if ($e == 11) {
        return false;
    }
    return true;
}

/**
 * Read from the websocket
 *
 * @param array $handle
 * @return string Stuff thats read, '' if nothing there or false on error
 */
function ws_read($handle)
{
    $out_buffer = "";
    do {
        // Read header
        $header = socket_read($handle['client'], 2);
        if ($header === false) {
            if (perm_error($handle)) {
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
        if ($opcode == 8) {
            return false;
        }

        // Get payload length extensions
        $ext_len = 0;
        if ($payload_len >= 0x7E) {
            $ext_len = 2;
            if ($payload_len == 0x7F) {
                $ext_len = 8;
            }
            $ext = socket_read($handle['client'], $ext_len);
            if ($ext === false) {
                if (perm_error($handle)) {
                    return false;
                } else {
                    return "";
                }
            }

            // Set extented paylod length
            $payload_len = 0;
            for ($i = 0; $i < $ext_len; $i++) {
                $payload_len += ord($header[$i]) << ($ext_len - $i - 1) * 8;
            }
        }

        // Get Mask key
        if ($masked) {
            $mask = socket_read($handle['client'], 4);
            if ($mask === false) {
                if (perm_error($handle)) {
                    return false;
                } else {
                    return "";
                }
            }
        }

        // Get payload
        $frame_data = '';
        do {
            $frame = socket_read($handle['client'], $payload_len);
            if ($frame === false) {
                if (perm_error($handle)) {
                    return false;
                } else {
                    return "";
                }
            } // die("Reading from websocket failed.");
            $payload_len -= strlen($frame);
            $frame_data .= $frame;
        } while ($payload_len > 0);

        // if opcode ping, reuse headers to send a pong and continue to read
        if ($opcode == 9) {
            // Assamble header: FINal 0x80 | Opcode 0x02
            $header[0] = chr(($final ? 0x80 : 0) | 0x0A); // 0x0A Pong
            socket_write($handle['client'], $header . $ext . $mask . $frame_data);

            // Recieve and unmask data
        } elseif ($opcode < 3) {
            $data = "";
            $data_len = strlen($frame_data);
            if ($masked) {
                for ($i = 0; $i < $data_len; $i++) {
                    $data .= $frame_data[$i] ^ $mask[$i % 4];
                }
            } else {
                $data .= $frame_data;
            }
            $out_buffer .= $data;
        }

        // wait for Final
    } while (!$final);

    return $out_buffer;
}



/**
 * Upgrade the HTTP socket to a websocket
 *
 * @param Socket $client socket
 * @return void
 */
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
    socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 100));
};

/**
 * Write to websocket
 *
 * @param array $handle
 * @param string $msg
 * @return void
 */
function ws_write($handle, $msg)
{
    if (empty($handle['client'])) {
        return;
    }
    
    $a = str_split($msg, 125);
    if (count($a) == 1) {
        $data = "\x81" . chr(strlen($a[0])) . $a[0];
    } else {
        $data = "";
        foreach ($a as $o) {
            $data .= "\x81" . chr(strlen($o)) . $o;
        }
    }

    socket_write($handle['client'], $data, strlen($data));
}

/**
 * Create the websocket - well actually it just creates an array, and the other functions
 * (ws_poll()) create the listening socket and upgrades it based on this array.
 *
 * @param [type] $port
 * @param boolean $debug Set to true to get lots of info on stdout
 * @return void
 */
function ws_create($port, $debug = false)
{
    $handle = [
        'port' => $port,
        'server' => null,
        'client' => null,
        'debug' => $debug,
    ];
    return $handle;
}

/**
 * Try to get something from a websocket. The websocket will be created if required based on
 * the handle.
 *
 * @param [type] $handle
 * @return string the message got, or ''
 */
function ws_poll(&$handle)
{
    if ($handle['server'] == null) {
        @$server = socket_create_listen($handle['port']);
        if (!empty($server)) {
            $handle['server'] = $server;
            socket_set_nonblock($handle['server']);
        } else {
            return '';
        }
    }

    if ($handle['client'] == null) {
        if ($handle['debug']) {
            echo "Not connected ";
        }
        if (empty($handle['server'])) {
            return '';
        }
        // Accept
        $client = socket_accept($handle['server']);
        if (!empty($client)) {
            if ($handle['debug']) {
                echo "Accepted ";
            }
            $handle['client'] = $client;
            change_to_websocket($handle['client']);
            if ($handle['debug']) {
                echo "Changed to WS ";
            }
        }
    }

    if ($handle['client'] != null) {
        if ($handle['debug']) {
            echo "Connected\n";
        }
        $read = ws_read($handle);
        if ($read === false) {
            if ($handle['debug']) {
                echo "Got false from open socket ";
            }
            $handle['client'] = null;
        } else {
            if (!empty($read)) {
                return ($read);
            }
        }
    }

    return '';
}
