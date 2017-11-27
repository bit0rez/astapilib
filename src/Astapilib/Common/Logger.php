<?php

namespace Astapilib\Common;

use Psr\Log\LoggerInterface;

// TODO: Use Monolog instead
class Logger implements LoggerInterface
{
    const MODE_CONSOLE = 'console';

    /** @var string */
    private $mode;

    /** @var int */
    private $verbose;

    public function __construct($verbose = 0, $mode = self::MODE_CONSOLE)
    {
        $this->verbose = (int) $verbose;
        $this->mode = (string) $mode;
    }

    public function setVerbose($level)
    {
        $this->verbose = (int) $level;
    }

    public function getVerbose()
    {
        return $this->verbose;
    }

    public function setMode($mode)
    {
        $this->mode = (string) $mode;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function log($level, $message, array $context = [])
    {
        if ($this->verbose < $level) {
            return;
        }

        switch ($this->mode) {
            case 'console':
                $this->console($message);
                break;
            default:

        }
    }

    public function emergency($message, array $context = [])
    {
        $this->log(0, $message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->log(1, $message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->log(2, $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log(3, $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log(4, $message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->log(5, $message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->log(6, $message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->log(7, $message, $context);
    }

    private function console($message)
    {
        echo date("M d H:i:s"),  substr(microtime(),1,6),' ',$message,PHP_EOL;
    }
}