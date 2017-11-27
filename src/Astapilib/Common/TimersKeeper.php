<?php

namespace Astapilib\Common;

class TimersKeeper
{
    /** @var  bool */
    private $registered = false;

    /** @var array|Timer[] */
    private $timers = [];

    /**
     * @return bool
     */
    public function register()
    {
        if (!$this->registered) {
            $this->registered = \register_tick_function(['TimersKeeper', 'poll']);
        }

        return $this->registered;
    }

    /**
     * @return bool
     */
    public function isRegistered()
    {
        return $this->registered;
    }

    public function poll()
    {
        foreach ($this->timers as $timer) {
            if ($timer->isStop() == false) {
                $timer->update();
            }
        }
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function unregisterTimer($id)
    {
        if (isset($this->timers[$id])) {
            unset($this->timers[$id]);
        }

        return $this;
    }

    /**
     * @param Timer $timer
     *
     * @return $this
     */
    public function registerTimer(Timer $timer)
    {
        $this->timers[$timer->getId()] = $timer;

        return $this;
    }

    /**
     * @param int      $time
     * @param callable $callable
     * @param bool     $type
     *
     * @return $this
     */
    public function addTimer($time, callable $callable, $type = false)
    {
        $timer = new Timer($time, $callable, $type);
        $timer->setKeeper($this);
        $this->timers[$timer->getId()] = $timer;

        return $this;
    }
}