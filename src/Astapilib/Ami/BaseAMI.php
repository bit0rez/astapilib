<?php

namespace Astapilib\Ami;

use Astapilib\Common\TimersKeeper;
use Psr\Log\LoggerAwareTrait;

class BaseAMI
{
    use LoggerAwareTrait;

    /** @var  TimersKeeper */
    protected $timersKeeper;

    protected $conn_handle = false;
    protected $inbound_stream_buffer = [];
    protected $events = [];
    protected $responses = [];
    protected $event_handlers = [];
    protected $refresh_lock = false;

    //конструктор, настройка по умолчанию 
    public function __construct(TimersKeeper $keeper, $config = [])
    {
        $this->timersKeeper = $keeper;

        //default parameters
        if (!isset($config['keepalive'])) {
            $config['keepalive'] = true;
        }
        //parse parameters
        foreach ($config as $opt => $val) {
            if ($opt == 'autorefresh' and $val === true) {
                $this->timersKeeper->addTimer(0.5, [$this, 'refresh'], true);
            }
            if ($opt == 'keepalive' and $val === true) {
                $this->timersKeeper->addTimer(60, [$this, 'ping'], true);
            }
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    //подключение к серверу, инициализация
    public function connect($host, $login, $password)
    {
        //add default ami port
        if (count(explode(':', $host)) != 2) {
            $host .= ':5038';
        }
        //tcp connect
        $this->conn_handle = @stream_socket_client("tcp://" . $host, $errno, $errstr, 30);
        if ($errno !== 0) {
            $this->logger->critical('Could not connect to tcp socket. Reason: ' . $errstr);

            return false;
        }
        if (!is_resource($this->conn_handle)) {
            $this->logger->critical('Socket not created! Check host and port options. Value: ' . $host);

            return false;
        }
        stream_set_blocking($this->conn_handle, 0);
        $this->logger->warning('Socket connected');
        $this->logger->warning('Server greeting phrase: ' . stream_get_line($this->conn_handle, 1500, "\r\n"));
        $loginstatus = $this->login($login, $password);
        if ($loginstatus === false) {
            stream_socket_shutdown($this->conn_handle, STREAM_SHUT_RDWR);
            $this->conn_handle = false;

            return false;
        } else {
            return true;
        }
    }

    //отключение от сервера
    public function disconnect()
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        $this->logout();
        stream_socket_shutdown($this->conn_handle, STREAM_SHUT_RDWR);
        $this->conn_handle = false;
        $this->logger->warning('Socket disconnected');

        return true;
    }

    //посылает запрос ping для реализации механизма keepalive
    public function ping()
    {
        $response = $this->get_response($this->send_action('Ping'));
        $this->logger->notice('PING? PONG!: ' . date("H:i:s", floatval($response["Timestamp"])));

        return $response["Timestamp"];
    }

    //авторизация на сервере
    protected function login($login, $password)
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        $resp = $this->get_response($this->send_action('login', ['Username' => $login, 'Secret' => $password]));
        if ($resp['Response'] == 'Success') {
            $this->logger->warning('Authentication accepted');

            return true;
        } else {
            $this->logger->critical('Authentication failed');

            return false;
        }
    }

    //завершение сессии на сервере
    protected function logout()
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        $resp = $this->get_response($this->send_action('Logoff'));
        $this->logger->warning('Logout... Server goodbye phrase: ' . $resp['Message']);

        return true;
    }

    //низкоуровневое получение ответов по ID запроса
    protected function get_response($ActId)
    {
        $retval = false;
        for ($cnt = 0; $cnt < 500; $cnt++) {
            usleep(10000);
            if (isset($this->responses[$ActId])) {
                $retval = $this->responses[$ActId];
                unset($this->responses[$ActId]);
                break;
            }
            $this->refresh();
        }

        return $retval;
    }

    //добавление обработчика событий
    public function add_event_handler($event, $callback)
    {
        $event = strtolower($event);
        if (is_array($callback)) {
            $callbackname = get_class($callback[0]) . "->" . $callback[1];
        } else {
            $callbackname = $callback;
        }
        if (!is_callable($callback)) {
            $this->logger->error("${callbackname} does not exist! Nothing to add as event handler...");

            return false;
        }
        if (!isset($this->event_handlers[$event])) {
            $this->event_handlers[$event] = $callback;
            $this->logger->warning('Event handler for events type "' . $event . '" was added as callable "' . $callbackname . '"');

            return true;
        } else {
            $this->logger->log(
                3,
                'Event handler for events type "' . $event . '" already exist as callable "' . $this->event_handlers[$event] . '"'
            );

            return false;
        }
    }

    //удаление обработчика событий
    public function remove_event_handler($event)
    {
        $event = strtolower($event);
        if (isset($this->event_handlers[$event])) {
            unset($this->event_handlers[$event]);
            $this->logger->warning('Event handler for events type "' . $event . '" was removed');

            return true;
        } else {
            $this->logger->error('Event handler for events type "' . $event . '" not exist');

            return false;
        }
    }

    //получение callback обработчика
    public function get_event_handler($event)
    {
        $event = strtolower($event);
        if (isset($this->event_handlers[$event])) {
            return $this->event_handlers[$event];
        } else {
            return false;
        }
    }

    //подписка на события ami
    public function enable_events($toggle = false)
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        if ($toggle === true) {
            $eventlist = 'on';
        } else {
            $eventlist = 'off';
        }
        $ActId = $this->send_action('Events', ['Eventmask' => $eventlist]);

        $res = $this->get_response($ActId);
        if (isset($res['Events'])) {
            if ($res['Events'] == 'On') {
                $this->logger->warning('Events enabled');

                return true;
            }
        }
        $this->logger->warning('Events disabled');

        return false;
    }

    //обработчик событий
    protected function event_poller()
    {
        foreach ($this->events as $index => $event) {

            $event_name = strtolower($event['Event']);
            if (isset($this->event_handlers[$event_name])) {
                $run_handler = $this->event_handlers[$event_name];
            } elseif (isset($this->event_handlers['*'])) {
                $run_handler = $this->event_handlers['*'];
            } else {
                $run_handler = false;
            }
            if (is_array($run_handler)) {
                $run_handler_name = get_class($run_handler[0]) . "->" . $run_handler[1];
            } else {
                $run_handler_name = $run_handler;
            }
            if ($run_handler === false) {
                $this->logger->info("Got event '${event_name}', but no handler for processing it.");
            } else {
                $this->logger->notice("Got event '${event_name}', runing '${run_handler_name}' handler for processing it.");
                call_user_func($run_handler, $event_name, $event);
            }
            unset($this->events[$index]);
        }
    }

    //низкоуровневая отправка запросов
    protected function send_action($action, $params = [])
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        if (!is_string($action)) {
            return false;
        }
        if (!is_array($params)) {
            return false;
        }
        if (!isset($params['ActionID'])) {
            $params['ActionID'] = uniqid();
        }
        $packet = 'Action: ' . $action . "\r\n";
        foreach ($params as $param => $param_value) {
            $packet .= $param . ': ' . $param_value . "\r\n";
        }
        $packet .= "\r\n";
        stream_socket_sendto($this->conn_handle, $packet);
        $this->refresh();

        return $params['ActionID'];
    }

    //обновление данных от сервера
    public function refresh()
    {
        if ($this->refresh_lock) {
            return null;
        }
        $this->refresh_lock = true;
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }

        while ($this->update_inbound_stream()) {
            $this->parse_inbound_stream_buffer();
        }

        $this->event_poller();
        $this->refresh_lock = false;

        return true;
    }

    //получение одной пачки данных из входящего потока от сервера в буфер пачек
    protected function update_inbound_stream()
    {
        if (!is_resource($this->conn_handle)) {
            $this->logger->alert('Call method ' . __METHOD__ . ' failure. TCP connection is not established.');

            return false;
        }
        while (true) {
            $raw_data = stream_get_line($this->conn_handle, 1500,
                "\r\n"); //получение сырых данных с парсингом по переводу строк
            if ($raw_data === '') {
                break;
            } //пустая строка означает конец пакета
            if ($raw_data === false) {
                return false;
            } //false означает осутствие данных
            $inbound_packet[] = $raw_data;  //формирование пакета для помещения во входной буфер
        }
        if (isset($inbound_packet)) //если пакет сформирован (а бывает и наоборот), то помещаем в буфер, иначе считаем что данных нет
        {
            $this->inbound_stream_buffer[] = $inbound_packet;

            return true;
        } else {
            return false;
        }
    }

    //парсер пачек извлекаемых из буфера и помещаемых в буферы ответов и очередей
    protected function parse_inbound_stream_buffer()
    {
        foreach ($this->inbound_stream_buffer as $index => $inbound_packet) {
            $pack = [];
            foreach ($inbound_packet as $line) {
                $parse_result = preg_match('/(^.[^ ]*): (.*)/', $line, $parsed_line);
                if ($parse_result === 1) {
                    $pack[$parsed_line[1]] = $parsed_line[2];
                } else {
                    $pack['RAW'] = $line;
                }
            }
            if (isset($pack['Response'])) {
                if ($pack['Response'] == "Error") {
                    $this->logger->error('ERROR RESPONSE: ' . $pack["Message"]);
                }
                $this->responses[$pack['ActionID']] = $pack;
            }
            if (isset($pack['Event'])) {
                $this->events[] = $pack;
            }
            unset($pack);
            unset($this->inbound_stream_buffer[$index]);
        }
    }
}