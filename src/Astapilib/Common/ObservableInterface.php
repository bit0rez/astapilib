<?php

namespace Astapilib\Common;

interface ObservableInterface
{
    public function registerObserver(ObserverInterface $obj);
    public function unregisterObserver(ObserverInterface $obj);
    public function notifyObservers();
}