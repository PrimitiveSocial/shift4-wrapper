<?php

namespace PrimitiveSocial\Shift4Wrapper\Facades;

use Illuminate\Support\Facades\Facade;

class Shift4Wrapper extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'shift4wrapper';
    }
}
