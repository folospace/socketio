<?php

namespace Folospace\Socketio\Facades;

use Illuminate\Support\Facades\Facade;

class Socketio extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'socketio';
    }
}
