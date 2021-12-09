# simple-php-and-js-websockets
A implementation of websockets using php and js. The php is server side, and the js is client.

Nothing fancy, no dependancies(except the test html pages uses a jquery cdn - does that count as a dependancy?!) 

Run the test_ws_server.php on the server, it will listen on port 12345 which is hard coded in the test script. The main work is 
done in websocket.php. Look at the test script for usage.

Load the test_ws_client.html in a browser, its main functionality is in the websocket.js. The other js in the html file is just
calling that script and showing the results. The links shown are connect, send, ask and close. send sends a single line of text
to the server side. ask sends a different line of text which prompts the test server to send a message back, which is then shown.
The test reply includes a random number from the server php, so you can see its working.

Some snippets of code was taken from elsewhere, all open source I belive. The rest is just what I put together for my own
project. 

Hope it helps someone!

# To use
## Client side: js example

    <script  src="websocket.js"></script>
    ...
    <script>
    // host
    var host='localhost';
    
    // port
    var port='12345';
    
    // Create websocket
    var ws=get_ws(host,port,function(m) {
       // Incoming message handler here
       console.log('Client was sent: '+m);
    });
	
	// Send something
	ws.send('Hello Server side');
	
	// Close
	ws.close();

## Server side: php example

    <?php  
    require_once('websocket.php');  
    // Port
    $port='12345';
    
    // Create a socket and start listening
    $handle=ws_create($port);  
    
    // Loop forver processing requests. Obviously you can make your code quit the loop
    // when suits your requirements.
    while  (true)  {  
	    $read=ws_poll($handle);  
		// Did we get anything?	
	    if  ($read!='')  {  
		    // Process message from client side (in this case send it back!)
		    ws_write($handle, "Got $read");  
		{
		// Do other stuff server side, best not to just loop back or you will tie up
		// the cpu. So maybe a short sleep...  
	    usleep(2000);  
    }


TODO
- Remove all the console.logs
- Possibly make it possible to select which interface the server binds to
- Tidy up commented out debugging code
