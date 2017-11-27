<?php

namespace Astapilib\Common;

abstract class Singleton {
    protected static $instances = [];

    protected function __construct(){}
    private function __clone(){}
    private function __wakeup(){}

    public static final function getInstance()
    {
        $class = get_called_class();
        if(!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }
}