# Socketio

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]

<p>A simple socketio server for Laravel.</p>
<p>Require php package <a href='https://github.com/nrk/predis'><i>predis</i></a>. </p>
<p>Require php extension <a href='https://github.com/swoole/swoole-src'><i>swoole</i></a>.</p>

## Installation

Via Composer

``` bash
$ composer require folospace/socketio
```

## Usage
### server commands
``` bash
$ php artisan socketio start        //start server
$ php artisan socketio start --d    //start server daemonize
$ php artisan socketio stop         //stop server
$ php artisan socketio status       //server status
```
### register events
``` bash
namespace App\Providers;

use Folospace\Socketio\Facades\Socketio;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (php_sapi_name() == 'cli') {
            Socketio::on('connection', function ($socket, $request) {
                print_r($request);
                echo 'connection:'.$socket->id.PHP_EOL;
            });

            Socketio::on('disconnect', function ($socket) {
                echo 'disconnect:'.$socket->id.PHP_EOL;
                //$this->onLogout($socket->id);
            });
            
            Socketio::on('login', function ($socket, $data) {
                $userId = $data['token']; //parse user id from client token
                if ($userId) {
                    //Socketio::login($socket->id, $userId);
                    $socket->emit('login', ['error_code' => 0, 'message' => 'login success']);
                } else {
                    $socket->emit('login', ['error_code' => 1, 'message' => 'invalid token']);
                }
            });
            
            Socketio::on('test', function ($socket, $data) {
                $socket->emit('test', 'hello there');
            });
            
            Socketio::on('private_event_with_ack', function ($socket, $data, $ack) {
                if (Socketio::getUserIdByClient($socket->id)) {
                    //do sth after login.
                    if ($ack) {
                        $ack('done');
                    }
                } else {
                    //disconnect guest.
                    $socket->disconnect();
                }
            });
        }
    }
}
```

### Test
``` bash

use Folospace\Socketio\Facades\Socketio;

//after socket login, send data to user from anywhere
Socketio::emitToUser($userId, 'test', ['message' => 'I am server']);

//after server start, connect to local server
Route::get('/', function () {
    $client = new \Folospace\Socketio\Foundation\SocketioClient('127.0.0.1', 3001);

    $client->emit('test', 'hello');
    //sleep(3);
    $ret = $client->receive();

    dd($ret);
});


```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email folospace@gmail.com instead of using the issue tracker.

## Credits

- [magacy][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/folospace/socketio.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/folospace/socketio.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/folospace/socketio/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/folospace/socketio
[link-downloads]: https://packagist.org/packages/folospace/socketio
[link-travis]: https://travis-ci.org/folospace/socketio
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/folospace
[link-contributors]: ../../contributors
