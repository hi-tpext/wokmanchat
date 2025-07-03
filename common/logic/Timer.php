<?php

namespace wokmanchat\common\logic;

class Timer
{
    /**
     * Add a timer.
     *
     * @param float    $time_interval
     * @param callable $func
     * @param mixed    $args
     * @param bool     $persistent
     * @return int|false
     */
    public static function add($time_interval, $func, $args = [], $persistent = true)
    {
        if (class_exists(\Workerman\Timer::class)) {
            return \Workerman\Timer::add($time_interval, $func, $args, $persistent);
        } else if (class_exists(\Workerman\Lib\Timer::class)) {
            return \Workerman\Lib\Timer::add($time_interval, $func, $args, $persistent);
        }

        return false;
    }
}
