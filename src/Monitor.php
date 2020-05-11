<?php

namespace toluban\MoleLog;

use yii\helpers\ArrayHelper;
use yii\web\Request;
use yii\base\Application;
use toluban\MoleLog\Exception\OperationException;

/**
 * Class Monitor
 * Author: guoliuyong
 * Date: 2020-05-11 15:16
 */
class Monitor
{
    /**
     * @var array
     */
    private $apis   = [];

    /**
     * @var array
     */
    private $tables = [];

    /**
     * @var string
     */
    private $currentApi;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $params;

    /**
     * @var Application
     */
    private $app;

    /**
     * @var int
     */
    private $startMicroTime;

    /**
     * @var array
     */
    private $fields = [];

    /**
     * @param Application $app
     * @return $this
     */
    public function initParams($app)
    {
        $this->app            = $app;
        $this->request        = $app->getRequest();
        $this->currentApi     = $this->request->pathInfo;
        $this->method         = $this->request->method;
        $this->params         = $this->request->get();
        $this->startMicroTime = $this->getMicroTime();

        if ($this->request->isPost || $this->request->isPut || $this->request->isDelete) {
            $this->params = array_merge($this->params, $this->request->bodyParams);
        }
        return $this;
    }

    /**
     * @param $configPath
     * @return $this
     * @throws OperationException
     */
    public function parseConfig($configPath)
    {
        if (!file_exists($configPath)) {
            throw new OperationException('配置文件不存在');
        }
        $monitorConfig = require($configPath);
        $this->apis    = $monitorConfig['api'] ?? [];
        $this->tables  = $monitorConfig['tables'] ?? [];
        return $this;
    }

    /**
     * @return bool
     */
    public function isMonitorApi() : bool
    {
        return isset($this->apis[$this->currentApi]);
    }

    /**
     * @param $table
     * @return bool
     */
    public function isMonitorTable($table) : bool
    {
        return isset($this->tables[$table]);
    }

    /**
     * @param $table
     * @return array
     */
    public function getMonitorFields($table) : array
    {
        return $this->tables[$table] ?? [];
    }

    /**
     * @return string
     */
    public function getApiName()
    {
        return $this->apis[$this->currentApi]['name'] ?? '';
    }

    /**
     * @return string
     */
    public function getApiType()
    {
        return $this->apis[$this->currentApi]['type'] ?? '';
    }

    /**
     * @return string
     */
    public function getCurrentApi()
    {
        return $this->currentApi;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->app->id;
    }

    /**
     * 获取客户端的真实IP
     *
     * @return array|false|mixed|string
     */
    public function getRealIP()
    {
        $ip = '';
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $remote_addr = getenv('HTTP_X_FORWARDED_FOR');
            $tmp_ip = explode(',', $remote_addr);
            $ip = $tmp_ip[0];
        } elseif (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('REMOTE_ADDR')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * @return int
     */
    public function getStartMicroTime()
    {
        return $this->startMicroTime;
    }

    /**
     * 获取毫秒级时间戳
     *
     * @return float
     */
    public function getMicroTime()
    {
        list($userSec, $sec) = explode(" ", microtime());
        return (float)sprintf('%.0f', (floatval($userSec) + floatval($sec)) * 1000);
    }

    /**
     * 解析监控字段
     *
     * @param $columns
     * @param $table
     * @return array
     */
    public function parseMonitorFields($columns, $table)
    {
        $data = [];
        $monitorFields = $this->getMonitorFields($table);

        // 如果有字段为*，则说明要全部字段
        if (in_array('*', $monitorFields)) {
            foreach ($columns as $column) {
                $data[$column]['type'] = CConst::JSON_FALSE;
            }
            return $data;
        }

        if (empty($monitorFields)) {
            return $data;
        }

        foreach ($monitorFields as $monitorField) {
            // 解析值为json, 匹配 aa.bb.cc
            if(preg_match('/([^\.]+)\.(.+)/', $monitorField, $match)) {
                $data[$match[1]]['keys'][] = $match[2];
                $data[$match[1]]['type'] = CConst::JSON_TRUE;
                continue;
            }

            $pattern = null;

            // 匹配*开头，且*结尾
            if(preg_match('/^\*(.+)\*$/', $monitorField, $match)) {
                $pattern = "/.+{$match[1]}.+/";
            }

            // 匹配*开头
            if(preg_match('/^\*(.+[^\*]$)/', $monitorField, $match)) {
                $pattern = "/.+{$match[1]}$/";
            }

            // 匹配*结尾
            if(preg_match('/(^[^\*].+)\*$/', $monitorField, $match)) {
                $pattern = "/^{$match[1]}.+/";
            }

            if($pattern != null) {
                foreach ($columns as $column) {
                    if(preg_match($pattern, $column, $match)) {
                        $data[$column]['type'] = CConst::JSON_FALSE;
                    }
                }
                continue;
            }

            // 正常的
            $data[$monitorField]['type'] = CConst::JSON_FALSE;
        }
        return $data;
    }
}