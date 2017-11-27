Asterisk AMI\AGI library for PHP
---

### How to use

1) Install dependencies with [**composer**](https://getcomposer.org/)
2) Include *vendor/autoload.php*
3) Create Logger: `$logger = new Astapilib\Common\Logger()`
4) Create TimersKeeper: `$keeper = new Astapilib\Common\TimersKeeper()`
5) Register it: `$keeper->register()`
6) Create AMI\AGI: `$ami = new Astapilib\Ami\AMI()` or `$agi = new Astapilib\Agi\AGI()`
7) Set logger: `$ami->setLogger($logger)`
8) ...
9) Profit
