# simple-php-and-js-websockets
An implementation of websockets using php and js. The php is server side, and the js is client.

Nothing fancy, there are no dependancies you need to install.

Some snippets of code have been taken from elsewhere, but all are open source I belive. The rest is just what I put together for my own
project. 

Seems to work for my project, hope it helps someone else too!

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

# How to use the included demo
The test scripts included show both sides of the websocket working with two way messages. You can experiment to see how it handles various situations. The php side (server) displays messages on stdout and the js side (client) logs messages to the console.
 - Checkout the repo into location a web server can see, so that
   something like http://localhost/test_ws_client.html loads the client
   side.  
- Open your browsers console logs to better see whats going on.
 - Using a shell navigate to the same directory on the web server and run the server side
   from the command line with **$ php ws_test_server.php**
 - Back in the browser, clicking ***connect*** will connect to the server, ***send*** and ***ask***
   will send messages to the server, and ***close*** will close the connection. (***ask*** sends a message which is hard coded into the test server script to make it send a reply.)

# TODO
- Remove all the console.logs
- Maybe make it possible to select which interface the server binds to
- Tidy up commented out debugging code
