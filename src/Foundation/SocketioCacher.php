<?php

namespace Folospace\Socketio\Foundation;

use Illuminate\Support\Facades\Redis;


class SocketioCacher
{
    const CACHE_CLIENT_TO_USER = 'CACHE_CLIENT_TO_USER';
    const CACHE_USER_TO_CLIENT = 'CACHE_USER_TO_CLIENT';

    private $redisConnection;

    private static $instance;


    private function __construct()
    {
        $this->redisConnection = config('socketio.redis');
    }

    /**
     * @return SocketioCacher
     */
    public static function getInstance()
    {
        return self::$instance ?: (self::$instance = new self);
    }

    /**
     * @return int
     */
    public function flush()
    {
        return Redis::resolve($this->redisConnection)->del([self::CACHE_CLIENT_TO_USER, self::CACHE_USER_TO_CLIENT]);
    }

    /**
     * @param $node
     * @param $package
     * @return mixed
     */
    public function publish($node, $package)
    {
        return Redis::connection($this->redisConnection)->publish($node, json_encode($package));
    }

    /**
     * @param $engine
     * @param $node
     */
    public function subscribe($engine, $node)
    {
        Redis::connection($this->redisConnection)->subscribe($node, function ($data) use ($engine)
        {
            if ($data = json_decode($data, true)) {
                if ($clientId = intval($data['forwarding']['client_id'])) {
                    try {
                        if ($data['event'] === 'disconnect') {
                            $engine->push($clientId, '41'); //notice client to disconnect
                            $engine->after(2, function () use ($engine, $clientId)
                            {
                                $engine->close($clientId);
                            });
                        } else {
                            $engine->push($clientId, '42' . json_encode([$data['event'], $data['data']]));
                        }
                    } catch (\Exception $e) {
                        $engine->close($clientId);
                    }
                }
            }
        });
    }

    /**
     * @param $clientId
     * @param $userId
     * @param $node
     * @return bool
     */
    public function login($clientId, $userId, $node)
    {

        $this->logoutByUserId($userId);

        $this->logoutByClientId($clientId, $node);

        $client = $node . ':' . $clientId;

        Redis::connection($this->redisConnection)->hset(self::CACHE_CLIENT_TO_USER, $client, $userId);
        Redis::connection($this->redisConnection)->hset(self::CACHE_USER_TO_CLIENT, $userId, $client);

        return true;
    }


    /**
     * @param $clientId
     * @param $node
     * @return string
     */
    public function getUserIdByClient($clientId, $node)
    {
        return Redis::connection($this->redisConnection)->hget(self::CACHE_CLIENT_TO_USER, $node . ':' . $clientId);
    }

    /**
     * @param $userId
     * @return array
     */
    public function getClientByUserId($userId)
    {
        if (!$client = Redis::connection($this->redisConnection)->hget(self::CACHE_USER_TO_CLIENT, $userId)) {
            return [];
        }

        $client = explode(':', $client);

        return [
            'host' => $client[0],
            'port' => $client[1],
            'client_id' => $client[2],
        ];
    }

    /**
     * @param $clientId
     * @param $node
     * @param bool $recursive
     * @return bool
     */
    public function logoutByClientId($clientId, $node, $recursive = true)
    {
        $client = $node . ':' . $clientId;

        if ($recursive && $userId = $this->getUserIdByClient($clientId, $node)) {
            $this->logoutByUserId($userId, false);
        }

        Redis::connection($this->redisConnection)->hdel(self::CACHE_CLIENT_TO_USER, [$client]);

        return true;
    }

    /**
     * @param $userId
     * @param bool $recursive
     * @return bool
     */
    public function logoutByUserId($userId, $recursive = true)
    {
        if ($recursive && $client = $this->getClientByUserId($userId)) {
            $this->logoutByClientId($client['client_id'], $client['host'] . ':' . $client['port'], false);
        }

        Redis::connection($this->redisConnection)->hdel(self::CACHE_USER_TO_CLIENT, [$userId]);

        return true;
    }

    /**
     * @return array
     */
    public function getAllOnlineUserIds()
    {
        return Redis::connection($this->redisConnection)->hkeys(self::CACHE_USER_TO_CLIENT) ?: [];
    }

}
