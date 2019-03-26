<?php

class USRIoTDevice extends stdClass
{
    public $ip;
    public $port;
    public $password;
    private $latestCommand = 0;
    private $socket = 0;
    private $connected = false;
    private $authorized = false;
    public $debug = false;
    public $outputs = array();
    public $inputs = array();

    function __construct($ip, $port = 8899, $password = 'admin')
    {
        $this->ip = $ip;
        if (!$port) {
            $port= 8899;
        }
        $this->port = $port;
        $this->password = $password;
    }

    function turnOn($port)
    {
        $this->sendCommand(0x02, (int)$port);
        $this->readConnection();
        sleep(1);
        $this->sendCommand(0x02, (int)$port);
        $this->readConnection();
    }

    function turnOff($port)
    {
        $this->sendCommand(0x01, (int)$port);
        $this->readConnection();
        sleep(1);
        $this->sendCommand(0x01, (int)$port);
        $this->readConnection();
    }

    function getPorts()
    {
        $this->sendCommand(0x0a); // outputs
        sleep(1);
        $this->readConnection();
        /*
        $started=time();
        while(count($this->outputs)==0 && (time()-$started)<3) {
            usleep(500);
            $this->readConnection();
        }
        */
        /*
        $this->sendCommand(0x14); // inputs
        sleep(1);
        $this->readConnection();
        */
        /*
        $started=time();
        while(count($this->inputs)==0 && (time()-$started)<3) {
            usleep(500);
            $this->readConnection();
        }
        */
    }

    function getControllerInfo()
    {
        //$this->sendCommand();
        dprint("Coming soon...", false);
    }

    function sendCommand($command, $parameter = null)
    {
        $this->latestCommand = $command;
        $bytes = $this->buildPacket($command, $parameter);
        $this->sendData($bytes);
    }

    function buildPacket($command, $parameter)
    {

        if (!is_null($parameter)) {
            $parameterBuf[] = $parameter;
        } else {
            $parameterBuf = array();
            $parameter = 0;
        }
        $length = 2 + count($parameterBuf);
        $bytes = array();
        $bytes[] = 0x55;
        $bytes[] = 0xAA;
        $bytes[] = 0x00;
        $bytes[] = $length;
        $bytes[] = 0x00;
        $bytes[] = $command & 255;
        foreach ($parameterBuf as $param) {
            $bytes[] = $param;
        }
        $bytes[] = $length + $command + $parameter;
        return $bytes;
    }

    function sendData($bytes)
    {
        if (!$this->socket) {
            $this->openConnection();
            $this->authorize();
        }
        if (!$this->socket) return false;

        if ($this->debug) {
            echo "Sending: ";
            dprint(binaryToString(makePayload($bytes)), false);
        }
        DebMes("Sending: " . binaryToString(makePayload($bytes)), 'usriot');
        $data = makePayload($bytes);
        if (!socket_write($this->socket, $data, count($bytes))) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->debug) {
                echo("Couldn't write to socket: [$errorcode] $errormsg \n");
            }
            DebMes("Couldn't write to socket: [$errorcode] $errormsg", 'usriot');
            $this->reconnect();
        }
    }


    function dataReceived($bytes)
    {
        if ($this->debug) {
            echo "Data received: ";
            dprint(binaryToString(makePayload($bytes)), false);
        }
        DebMes('Received: ' . binaryToString(makePayload($bytes)), 'usriot');
        if (!$this->authorized && binaryToString(makePayload($bytes)) == '4f4b') {
            if ($this->debug) {
                dprint('Authorized!', false);
            }
            $this->authorized = true;
        } elseif ($this->authorized) {
            $length = $bytes[3] - 2;
            //dprint('len: '.$length,false);
            //$packetEnd = 5 +$length+2;
            $payload = array_slice($bytes, 5, $length + 1);
            //dprint('payload: '.binaryToString(makePayload($payload)),false);
            $parity = $bytes[5+$length+1];
            //dprint('parity: '.$parity,false);
            $parityCheck = $length + 2;
            for ($i = 0; $i < count($payload); $i++) $parityCheck += $payload[$i];
            $parityCheck &= 255;
            //dprint('parityCheck: '.$parityCheck,false);
            if ($parityCheck == $parity) {
                $cmd = $payload[0]-0x80;
                $params = array_slice($payload,1);
                if ($this->debug) {
                    dprint('CMD: '.$cmd." Params: ".binaryToString(makePayload(array_slice($payload,1))), false);
                }

                if ($cmd == 10) { // outputs data
                    $result='';
                    foreach($params as $param) {
                        $result.=str_pad(decbin($param),8,'0',STR_PAD_LEFT);
                    }
                    $result = strrev($result);
                    if ($this->debug) {
                        dprint("Outputs: ".$result,false);
                    }
                    for($i=0;$i<strlen($result);$i++) {
                        if (substr($result,$i,1)=='1') {
                            $this->outputs[$i]=1;
                        } else {
                            $this->outputs[$i]=0;
                        }
                    }
                }
                if ($cmd == 20) { // inputs data
                    $result='';
                    foreach($params as $param) {
                        $result.=str_pad(decbin($param),8,'0',STR_PAD_LEFT);
                    }
                    $result = strrev($result);
                    if ($this->debug) {
                        dprint("Inputs: ".$result,false);
                    }
                    for($i=0;$i<strlen($result);$i++) {
                        if (substr($result,$i,1)=='1') {
                            $this->inputs[$i]=1;
                        } else {
                            $this->inputs[$i]=0;
                        }
                    }
                }
            } else {
                if ($this->debug) {
                    dprint('Parity FAIL', false);
                }
            }

        }
    }

    function reconnect()
    {
        $this->closeConnection();
        $this->openConnection();
        $this->authorize();
    }

    function readConnection()
    {
        if (!$this->socket) return 0;
        $read = array();
        $read[0] = $this->socket;

        $write = NULL;
        $except = NULL;
        $num_changed_sockets = socket_select($read, $write, $except, 0, 1);

        if ($num_changed_sockets === false) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->debug) {
                echo("Couldn't select read socket: [$errorcode] $errormsg \n");
            }
            DebMes("Couldn't select read socket: [$errorcode] $errormsg", 'usriot');
            $this->reconnect();
            return false;
        }

        if ($num_changed_sockets > 0) {
            if (false !== ($bytes = socket_recv($this->socket, $in, 2048, 0))) {
                //echo "Read $bytes using socket_recv().\n";
                $hex_string = binaryToString($in);
                //dprint($hex_string, false);
                $tmp = explode('aa55', $hex_string);
                for ($i = 0; $i < count($tmp); $i++) {
                    if (!$tmp[$i]) continue;
                    if ($i == 0) {
                        $res_string = $tmp[$i];
                    } else {
                        $res_string = 'aa55' . $tmp[$i];
                    }
                    $bytes = HexStringToArray($res_string);
                    $this->dataReceived($bytes);
                }
            } else {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                if ($this->debug) {
                    echo("Couldn't select read data from socket: [$errorcode] $errormsg \n");
                }
                DebMes("Couldn't select read data from socket: [$errorcode] $errormsg", 'usriot');
            }
        }
    }


    function openConnection()
    {
        $this->connected = false;
        if (!($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->debug) {
                echo("Couldn't create socket: [$errorcode] $errormsg \n");
            }
            DebMes("Couldn't create socket: [$errorcode] $errormsg", 'usriot');
            return false;
        }
        if (!socket_connect($this->socket, $this->ip, $this->port)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->debug) {
                echo("Could not connect socket (" . $this->ip . ":" . $this->port . ") : [$errorcode] $errormsg \n");
            }
            DebMes("Could not connect socket (" . $this->ip . ":" . $this->port . ") : [$errorcode] $errormsg", 'usriot');
            return false;
        }
        if ($this->debug) {
            dprint("Connected to " . $this->ip . ":" . $this->port, false);
        }
        DebMes("Connected to  " . $this->ip . ":" . $this->port, 'usriot');
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 10, "usec" => 0));
        $this->connected = true;
    }

    function authorize()
    {
        if ($this->socket) {
            $bytes = array();
            for ($i = 0; $i < strlen($this->password); $i++) {
                $bytes[] = ord(substr($this->password, $i, 1));
            }
            $bytes[] = 0x0D;
            $bytes[] = 0x0A;
            $this->sendData($bytes);
            usleep(500);
            $this->readConnection();
        }
    }

    function closeConnection()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
        $this->connected = false;
    }
}
