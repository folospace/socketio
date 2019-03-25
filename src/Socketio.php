<?php

namespace Folospace\Socketio;

use Folospace\Socketio\Foundation\SocketioParser;
use Folospace\Socketio\Foundation\SocketioCacher;

class Socketio
{
    public $forwardingToken;
    protected $node;
    protected $debug;
    protected $events = [];


    public function __construct()
    {
        $this->forwardingToken = config('socketio.server.token');
    }

    /**
     * @param string $event
     * @param \Closure $closure
     */
    public function on(string $event, \Closure $closure)
    {
        $this->events[$event] = $closure;
    }

    /**
     * @param $server
     * @param $node
     * @param bool $damon
     */
    public function onEngineStart($server, $node, $damon = false)
    {
        $this->node = $node;
        $this->debug = !$damon;

        $this->registerBaseEvent();
        $this->registerCustomEvent();

        SocketioParser::getInstance()->bindEngine($server, $this->node);
    }


    public function registerBaseEvent()
    {
        SocketioParser::getInstance()->on('connection', function ($socket, $request)
        {

        });

        SocketioParser::getInstance()->on('ping', function ($socket)
        {

        });

        SocketioParser::getInstance()->on('forwarding', function ($socket, $data)
        {
            $this->onForwarding($data);
        });


        SocketioParser::getInstance()->on('disconnect', function ($socket)
        {
            $this->onLogout($socket->id);
        });

        SocketioParser::getInstance()->on('test', function ($socket, $data) {
            $socket->emit('test', 'hello there');
        });

        SocketIOParser::getInstance()->on('login', function ($socket, $data)
        {
            $userId = $data['token']; //parse userId from client token
            if ($userId) {
                if ($client = SocketioCacher::getInstance()->getClientByStaffId($userId)) {
                    $this->emitToClient($client, 'logout', 'old client should logout');
                }

                $this->login($socket->id, $userId);

                $socket->emit('login', [
                    'error_code' => 0,
                    'msg' => 'login success',
                ]);
            } else {
                $socket->emit('login', [
                    'error_code' => 1,
                    'message' => 'invalid token, login failed'
                ]);
                $socket->disconnect();
            }
        });
    }


    public function login($socketId, $userId)
    {
        return SocketioCacher::getInstance()->login($socketId, $userId, $this->node);
    }


    public function registerCustomEvent()
    {
        foreach ($this->events as $event => $closure) {
            SocketioParser::getInstance()->on($event, $closure);
        }
    }

    /**
     * get client
     * @param $userId
     * @return array ['host' => '127.0.0.1', 'port' => '3001', 'client_id' => 1]
     */
    public function getClientByUserId($userId)
    {
        return SocketioCacher::getInstance()->getClientByUserId($userId);
    }

    /**
     * get user id
     * @param $socketId
     * @return string
     */
    public function getUserIdByClient($socketId)
    {
        return SocketioCacher::getInstance()->getUserIdByClient($socketId, $this->node);
    }

    /**
     * get all online user ids
     * @return array
     */
    public function getAllOnlineUserId()
    {
        return SocketioCacher::getInstance()->getAllOnlineUserIds();
    }

    /**
     * broadcast to all online user
     * @param $event
     * @param $data
     */
    public function emitToAll($event, $data)
    {
        foreach ($this->getAllOnlineUserId() as $userId) {
            $this->emitToClient($this->getClientByUserId($userId), $event, $data);
        }
    }

    /**
     * disconnect user
     * @param $userId
     * @return bool|mixed
     */
    public function disconnectUser($userId)
    {
        return $this->emitToUser($userId, 'disconnect');
    }

    /**
     * @param $userId
     * @param $event
     * @param string $data
     * @return bool|mixed
     */
    public function emitToUser($userId, $event, $data = '')
    {
        if ($client = SocketioCacher::getInstance()->getClientByUserId($userId)) {
            return $this->emitToClient($client, $event, $data);
        }

        return false;
    }

    /**
     * @param $client
     * @param $event
     * @param $data
     * @return bool|mixed
     */
    public function emitToClient($client, $event, $data)
    {
        $toNode = $client['host'] . ':' . $client['port'];
        if ($toNode == $this->node) {
            try {
                return SocketioParser::getInstance()->emitTo($event, $data, $client['client_id']);
            } catch (\Exception $e) {
                SocketioCacher::getInstance()->logoutByClientId($client['client_id'], $this->node);
                return false;
            }
        } else {
            $client['token'] = $this->forwardingToken;

            return SocketioCacher::getInstance()->publish($toNode, [
                'forwarding' => $client,
                'event' => $event,
                'data' => $data
            ]);
        }
    }

    /**
     * @param $data
     * @return bool|mixed
     */
    public function onForwarding($data)
    {
        if (isset($data['forwarding']) && $data['forwarding']['token'] == $this->forwardingToken) {
            return $this->emitToClient($data['forwarding'], $data['event'], $data['data']);
        }
        return false;
    }


    /**
     * @param $socketId
     * @return bool|int
     */
    public function onLogout($socketId)
    {
        SocketioCacher::getInstance()->logoutByClientId($socketId, $this->node);
    }

    public function getNode()
    {
        return $this->node;
    }

    public function isDebug()
    {
        return $this->debug;
    }
}