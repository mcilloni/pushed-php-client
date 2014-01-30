<?php

class Pushed {

    public static $ACCEPTED = 'ACCEPTED', $NO = 'NO', $REJECTED = 'REJECTED', $YES = 'YES';

    public static function connectUnix($path) {

        $pushed = new Pushed();

        //Ok, C functions in a class, just, what?
        $pushed->mSocket = socket_create(AF_UNIX, SOCK_STREAM, SOL_TCP);

        // let's use ===, who knows what php can intepret as false with ==
        if($pushed->mSocket === FALSE) {
            //WHAT? socket_strerror(socket_last_error()) is just shit
            throw new PushedException(socket_strerror(socket_last_error())); 
        }

        if (!socket_connect($pushed->mSocket, $path)) {
            throw new PushedException(socket_strerror(socket_last_error()));
        }

        return $pushed;

    }

    public static function connectIp($localPort, $ipSix = true) {
        
        $pushed = new Pushed();

        $pushed->mSocket = socket_create($ipSix ? AF_INET6 : AF_INET, SOCK_STREAM, SOL_TCP);

        if($pushed->mSocket === FALSE) {
            throw new PushedException(socket_strerror(socket_last_error())); 
        }

        if (!socket_connect($pushed->mSocket, $ipSix ? "::1" : "127.0.0.1", $localPort)) {
            throw new PushedException(socket_strerror(socket_last_error()));
        }

        return $pushed;

    }

    private static function valid($string) {
        if(strpbrk($string, "\n\r\t\0") !== FALSE) {
            return FALSE;
        }
        
        return TRUE;
    }

    public $mSocket;

    public function addUser($id) {
        return $this->rawRequest('ADDUSER',"$id");
    }

    public function delUser($id) {
        return $this->rawRequest('DELUSER',"$id");
    }

    public function exists($id) {
        $resp = $this->rawRequest('EXISTS',"$id");

        if ($resp[0] == Pushed::$REJECTED) {
            throw new PushedException($resp[1]);
        }

        return $resp[0] === Pushed::$YES;
    }

    public function halt($timeout) {
        return $this->rawRequest('HALT',"$timeout");
    }

    public function push($id, $data) {
        return $this->rawRequest('PUSH',"$id", $data);
    }

    public function rawRequest($command, $args="", $data="") {

        if (!Pushed::valid($args) || !Pushed::valid($data)) {
            throw new PushedException('Request rejected because of invalid characters');
        }
    
        $msg = $command.' '.$args."\n".$data."\n";

        if(!socket_write($this->mSocket, $msg)) {
            throw new PushedException(socket_strerror(socket_last_error()));
        }

        $resp = socket_read($this->mSocket, 2048, PHP_NORMAL_READ);

        if(!$resp) {
            throw new PushedException(socket_strerror(socket_last_error()));
        }

        return $this->parseResponse($resp);

    }

    public function subscribe($id, $service, $devId) {
        return $this->rawRequest('SUBSCRIBE',"$id $service:$devId");
    }

    public function subscribed($id, $service, $devId = FALSE) {
        $args = "$id $service";

        if($devId !== FALSE) {
            $args .= ":$devID";
        }

        $resp =  $this->rawRequest('SUBSCRIBED', $args);


        if ($resp[0] == Pushed::$REJECTED) {
            throw new PushedException($resp[1]);
        }

        return $resp[0] === Pushed::$YES;
    }

    public function unsubscribe($id, $service, $devId) {
        return $this->rawRequest('UNSUBSCRIBE',"$id $service:$devId");
    }

    private function parseResponse($recvd) {
    
        $ret = explode(' ', $recvd, 2);

        if(count($ret) != 2) {
            throw new PushedException('Wrong number of parameters from response');
        }

        $ret[1] = trim($ret[1]);

        return $ret;

    }

    private function __construct() {}

}

class PushedException extends Exception {

    public function __construct($msg) {
        parent::__construct($msg);
    }

}

?>
