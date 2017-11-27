<?php

namespace Astapilib\Agi;

class BaseAGI
{
    protected $request = false;
    protected $last_response = null;

    public function __construct()
    {
        $this->request = $this->processRequest();
        if ($this->request === false) {
            return false;
        }
    }

    //получение запроса
    protected function processRequest()
    {
        while (true) {
            $line = stream_get_line(STDIN, 1500, PHP_EOL); //получение сырых данных с парсингом по переводу строк
            if ($line === '') {
                break;
            } //пустая строка означает конец пакета
            if ($line === false) {
                return false;
            } //false означает осутствие данных
            $parse_result = preg_match('/(^.[^ ]*): (.*)/', $line, $parsed_line);
            if ($parse_result === 1) {
                $request[$parsed_line[1]] = $parsed_line[2];
            }
        }

        if (isset($request)) {
            return $request;
        } else {
            return false;
        }
    }

    //обработка комманды
    protected function processCmd($cmd)
    {
        fwrite(STDOUT, $cmd . PHP_EOL);
        $line = stream_get_line(STDIN, 1500, PHP_EOL); //получение сырых данных с парсингом по переводу строк
        $parse_result = preg_match('/(\d+)(?:.)(.*)/', $line, $parsed_line);
        if ($parse_result === 1) {
            $resp['code'] = (int) $parsed_line[1];
            $data = $parsed_line[2];
            if ($resp['code'] == 200) {
                $parse_result = preg_match_all("/(?:(?'rval'[^\ ]+=[^\ ]*))|(?:\s\((?'aval'.*?)\)(?:\s|$))/", $data,
                    $parsed_line, PREG_SET_ORDER);
                if ($parse_result != false) {
                    foreach ($parsed_line as $parsed_set) {
                        if (isset($parsed_set['rval']) && !isset($parsed_set['aval'])) {
                            $kv = explode('=', $parsed_set['rval']);
                            $resp[$kv[0]]['val'] = $kv[1];
                            $lastparam = $kv[0];
                        }
                        if (isset($parsed_set['aval']) && isset($lastparam)) {
                            $resp[$lastparam]['data'] = $parsed_set['aval'];
                        }
                    }
                }
                $this->last_response = $resp;

                return $resp;
            } else {
                $resp['error'] = $data;
                $this->last_response = $resp;

                return $resp;
            }
        } else {
            $this->last_response = false;

            return false;
        }
    }

    //получение обработанного запроса или его частей
    public function getRequest($key = null)
    {
        if ($this->request === false) {
            return false;
        }
        if ($key === null) {
            return $this->request;
        }
        if (!isset($this->request[$key])) {
            return false;
        } else {
            return $this->request[$key];
        }
    }

    //получение последнего ответа целиком
    public function getLastResponse()
    {
        return $this->last_response;
    }
}