<?php

namespace Folospace\Socketio\Commands;

use Folospace\Socketio\Foundation\SocketioCacher;
use Illuminate\Console\Command;
use Folospace\Socketio\Facades\Socketio as Server;

class Socketio extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'socketio {action} {--d}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = "A simple socketio server: start --d, status, stop";


    private $host;

    public function __construct()
    {
        parent::__construct();
        $this->host = $this->getHost();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        switch ($action) {
            case 'restart':
            case 'start':
                $this->stop();
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'status':
                $this->status();
                break;
        }
    }


    /**
     * start server
     */
    public function start()
    {

        $port = config('socketio.server.port', '3001');
        $engine = new \swoole_websocket_server('0.0.0.0', $port);
        $daemonize = intval($this->option('d'));

        Server::onEngineStart($engine, $this->host . ':' . $port, $daemonize);

        $config = config('socketio.server.config');
        $config['daemonize'] = $daemonize;
        $engine->set($config);

        $this->info('server start');
        $engine->start();
    }


    /**
     * stop server
     */
    public function stop()
    {
        SocketioCacher::getInstance()->flush();

        $sign = 'artisan socketio';
        if (intval(shell_exec(sprintf("ps -ef |grep '%s' |grep -v grep |grep -v %d |wc -l", $sign, getmypid()))) > 0) {
            shell_exec(sprintf("ps -ef |grep '%s' |grep -v grep |grep -v %d |awk {'print $2'} | xargs kill -9", $sign, getmypid()));
        }

        $this->info('server stop');
        sleep(1);
    }


    /**
     * print status
     */
    public function status()
    {
        $sign = 'artisan socketio';

        print_r(shell_exec(sprintf("ps -ef |grep '%s'|grep -v 'grep'|grep -v 'status'", $sign)));
    }


    /**
     * get host ip
     * @return string
     */
    private function getHost() {
        preg_match('/inet(.*)netmask/', shell_exec('ifconfig'), $match);

        return isset($match[1]) ? trim($match[1]) : config('socketio.server.host', '0.0.0.0');
    }
}
