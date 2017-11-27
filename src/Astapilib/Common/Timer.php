<?php

namespace Astapilib\Common;

class Timer
{
    /**
     * метка
     * @var string
     */
    private $id;

    /**
     * таймер
     * @var integer
     */
    private $time;

    /**
     * флаг остановленности true/false
     * @var  bool
     */
    private $stop;

    /**
     * задача
     * @var Callable
     */
    private $userCall;

    /**
     * параметр к задаче
     * @var array
     */
    private $userCallArg;

    /**
     * момент запуска таймера
     * @var integer
     */
    private $startTime;

    /**
     * оставшееся время
     * @var integer
     */
    private $ltime;

    /**
     * тип true регенерируемый, false не регенерируемый
     * @var bool
     */
    private $type;

    /**
     * Связь с хранителем
     * @var  TimersKeeper
     */
    private $keeper;

    /**
     * Timer constructor.
     *
     * @param integer  $time
     * @param Callable $callable
     * @param bool     $type
     */
    public function __construct($time, Callable $callable, $type = false)
    {
        $this->id = \uniqid();
        $this->time = (int) $time;
        $this->userCall = $callable;
        $this->userCallArg = [];
        $this->type = (bool) $type;
    }

    public function __destruct()
    {
        if ($this->keeper !== null) {
            $this->keeper->unregisterTimer($this->id);
        }
    }

    /**
     * @param TimersKeeper $keeper
     *
     * @return Timer
     */
    public function setKeeper(TimersKeeper $keeper)
    {
        $this->keeper = $keeper;

        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isStop()
    {
        return $this->stop;
    }

    /**
     * @return int
     */
    public function getTimeLeft()
    {
        return $this->ltime;
    }

    /**
     * @param int $time
     */
    public function setTimeLeft($time)
    {
        $this->time = (int) $time;
    }

    public function reset()
    {
        $this->startTime = \microtime(true);
        $this->stop = false;
    }

    public function start()
    {
        $this->reset();
        $this->stop = false;
    }

    public function stop()
    {
        $this->stop = true;
    }

    public function update()
    {
        $this->ltime = $this->startTime - microtime(true) + $this->time;
        if ($this->ltime <= 0) {
            $this->ltime = 0;
            $this->task();
            if ($this->type == false) {
                $this->stop();
            } else {
                $this->reset();
            }
        }
    }

    private function task()
    {
        \call_user_func_array($this->userCall, $this->userCallArg);
    }
}
