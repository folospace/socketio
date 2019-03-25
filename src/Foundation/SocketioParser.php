<?php

namespace Folospace\Socketio\Foundation;

use Closure;


class SocketioParser
{
    public $id;
    protected $engine;
    protected $events = [];
    protected $config;


    private $ackId;
    private static $instance;


    private function __construct()
    {
        $this->config = sprintf('0%s', json_encode(config('socketio.protocol.config')));
    }

    /**
     * @return SocketIOParser
     */
    public static function getInstance()
    {
        return self::$instance ?: (self::$instance = new self);
    }


    /**
     * register socketio event
     * @param $event
     * @param Closure $callback
     */
    public function on(string $event, Closure $callback)
    {
        $this->events[$event] = $callback;
    }

    /**
     * @param $engine
     * @param null $node
     */
    public function bindEngine($engine, $node = null)
    {
        $engine->on('Open', [$this, 'onOpen']);
        $engine->on('Message', [$this, 'onMessage']);
        $engine->on('Close', [$this, 'onClose']);

        if ($node) {
            $process = new \swoole_process(function ($process) use ($engine, $node)
            {
                SocketioCacher::getInstance()->subscribe($engine, $node);
            });
            $engine->addProcess($process);
        }
    }


    /**
     * @param $engine
     * @param $request
     */
    public function onOpen($engine, $request)
    {
        try {
            $this->engine = $engine;
            $this->id = $request->fd;

            $engine->push($request->fd, $this->config); //socket is open
            $engine->push($request->fd, '40');  //client is connected

            if (isset($this->events['connection'])) {
                $this->events['connection']($this, $request);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param $engine
     * @param $fd
     */
    public function onClose($engine, $fd)
    {
        try {
            $this->engine = $engine;
            $this->id = $fd;
            if (isset($this->events['disconnect'])) {
                $this->events['disconnect']($this);
            }
        } catch (\Exception $e) {
        }
    }


    /**
     * 消息响应
     * @param $engine
     * @param $frame
     */
    public function onMessage($engine, $frame)
    {
        try {
            $this->engine = $engine;
            $this->id = $frame->fd;

            if ($index = strpos($frame->data, '[')) {
                $code = substr($frame->data, 0, $index);
                $data = json_decode(substr($frame->data, $index), true);
            } else {
                $code = $frame->data;
                $data = '';
            }

            switch (strlen($code)) {
                case 0:
                    break;
                case 1:
                    switch ($code) {
                        case '2':   //client ping
                            $engine->push($frame->fd, '3'); //sever pong
                            if (isset($this->events['ping'])) {
                                $this->events['ping']($this);
                            }
                            break;
                    }
                    break;
                case 2:
                    switch ($code) {
                        case '41':   //client disconnect
                            $this->close();
                            break;
                        case '42':   //client message
                            if (is_array($data) && count($data) > 1 && is_string($data[0]) && isset($this->events[$data[0]])) {
                                $this->events[$data[0]]($this, $data[1]);
                            }
                            break;
                    }
                    break;
                default:
                    switch ($code[0]) {
                        case '4':   //client message
                            switch ($code[1]) {
                                case '2':   //client message with ack
                                    $this->ackId = substr($code, 2);
                                    $this->events[$data[0]]($this, $data[1], [$this, 'ack']);
                                    break;

                                case '3':   //client reply to message with ack
                                    break;
                            }
                            break;
                    }
                    break;
            }
        } catch (\Exception $e) {
        }
    }


    /**
     * @param $event
     * @param $data
     * @return mixed
     */
    public function emit($event, $data)
    {
        return $this->emitTo($event, $data, $this->id);
    }


    /**
     * @param $event
     * @param $data
     * @param $clientId
     * @return mixed
     */
    public function emitTo($event, $data, $clientId)
    {
        return $this->engine->push($clientId, '42' . json_encode([$event, $data]));
    }

    /**
     */
    public function disconnect()
    {
        $this->engine->push($this->id, '41'); //notice client to disconnect
        $this->close();
    }

    /**
     * @param $data
     */
    public function ack($data)
    {
        $payload = sprintf('43%s%s', $this->ackId, json_encode([$data]));
        $this->engine->push($this->id, $payload);
    }

    public function close()
    {
        $engine = $this->engine;
        $id = $this->id;

        $this->engine->after(2, function () use ($engine, $id)
        {
            $engine->close($id);
        });
    }
}

