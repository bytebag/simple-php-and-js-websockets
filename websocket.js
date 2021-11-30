function get_ws(message_handler) {
    var websock = {};
    websock.open = false;
    websock.host = 'ws://0.0.0.0:12345/websockets.php';
    console.log('Opening ws connection');
    websock.socket = new WebSocket(websock.host);

    websock.socket.onmessage = function (e) {
        console.log('Got a message');
        message_handler(e.data);
    };
    websock.socket.onerror = function (e) {
        websock.open = false;
        console.log('An error happened');
        //setTimeout(function () {
        //    console.log('In timeout');
        //    get_ws(message_handler);
        //},1000);
    }
    websock.socket.onopen = function (e) {
        websock.open = true;
        console.log('Opened');
    }
    websock.socket.onclose = function (e) {
        websock.open = false;;
        console.log('Closed');
        // setTimeout(get_ws(message_handler),10000);
    }
    websock.send = function (msg) {
        if (websock.open) {
            console.log('Sending ' + msg);
            websock.socket.send(msg);
        } else {
            console.log('Cant send ' + msg);
        }
    }
    websock.close = function () {
        websock.socket.close();
    }
    return websock;
}
