# simple-php-and-js-websockets
A implementation of websockets using php and js. The php is server side, and the js is client.

Nothing fancy, no dependancies(except the test html pages uses a jquery cdn - does that count as a dependancy?!) 

Run the test_ws_server.php on the server, it will listen on port 12345 which is hard coded in the test script. The main work is 
done in websocket.php. Look at the test script for usage.

Load the test_ws_client.html in a browser, its main functionality is in the websocket.js. The other js in the html file is just
calling that script and showing the results. The links shown are connect, send, ask and close. send sends a single line of text
to the server side. ask sends a different line of text which prompts the test server to send a message back, which is then shown.
The test reply includes a random number from the server php, so you can see its working.

Some of the code snippets was taken from elsewhere, all open source I belive. The rest is just what I put together for my own
project. 

Hope it helps someone!

TODO
- Remove hard coded port from websocket.js
- Remove all the console.logs
- Possibly make it possible to select which interface to bind to
- Tidy up commented out debugging code
