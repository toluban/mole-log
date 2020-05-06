<?php

namespace toluban\MoleLog\logger;

use Yii;
use yii\helpers\BaseJson;
use yii\web\Application;
use yii\web\Request;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

/**
 * Class OperationLog
 * Author: guoliuyong
 * Date: 2020-05-06 19:27
 */
class OperationLog extends Base
{
    /**
     * @var RecordLog
     */
    private $recordLog;

    /**
     * @var array
     */
    private $monitorApis = [];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Application
     */
    private $app;

    /**
     * OperationLog constructor.
     * @param RecordLog $recordLog
     */
    public function __construct(RecordLog $recordLog)
    {
        $this->recordLog = $recordLog;
    }

    /**
     * @param $monitorConfig
     * @return $this
     */
    public function setMonitorConfig($monitorConfig)
    {
        $monitorConfig     = require($monitorConfig);
        $this->monitorApis = $monitorConfig['api'] ?? [];
        $this->recordLog->setMonitorTables($monitorConfig['tables'] ?? []);
        return $this;
    }

    /**
     * @param $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @param $app
     * @return $this
     */
    public function setApp($app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * @return array
     */
    public function parseRequest()
    {
        $params = $this->request->get();
        if ($this->request->isPost || $this->request->isPut || $this->request->isDelete) {
            $params = array_merge($params, $this->request->bodyParams);
        }
        return ['method' => $this->request->method, 'params' => BaseJson::encode($params)];
    }

    /**
     * @param $model
     * @return $this
     */
    public function setRecordModel($model)
    {
        $this->recordLog->setModel($model);
        return $this;
    }

    /**
     * 获取需要记录的api信息
     *
     * @return mixed
     * @throws \yii\base\InvalidParamException
     */
    private function getMonitorApi()
    {
        return ArrayHelper::getValue($this->monitorApis, 'api.' . $this->request->pathInfo, false);
    }

    /**
     * 获取客户端的真实IP
     *
     * @return string
     */
    private function getRealIP()
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
     * 获取毫秒级时间戳
     *
     * @return float
     */
    private function getMicroTime()
    {
        list($userSec, $sec) = explode(" ", microtime());
        return (float)sprintf('%.0f', (floatval($userSec) + floatval($sec)) * 1000);
    }

    /**
     * 开启日志
     *
     * @param $event
     * @return $this|bool
     * @throws \yii\base\InvalidParamException
     */
    public function logBegin($event)
    {
        // 判断api是否需要监控,
        if (!$pathInfo = $this->getMonitorApi()) {
            return true;
        }
        $this->model = $this->getModel();
        $this->model->setAttributes(array_merge($this->parseRequest(), [
            'app'               => $this->app->id,
            'route'             => $this->request->pathInfo,
            'route_name'        => $pathInfo['name'],
            'type'              => $pathInfo['type'],
            'http_request_id'   => $_SERVER['HTTP_REQUEST_ID'] ?? '--',
            'ip'                => $this->getRealIP(),
            'exec_time'         => 0
        ]));

        return $this;
    }

    /**
     * 结束日志
     *
     * @param $event
     * @return $this|bool
     * @throws Exception
     * @throws \yii\base\InvalidParamException
     */
    public function logEnd($event)
    {
        // 判断api是否需要监控
        if (!$this->getMonitorApi()) {
            return true;
        }

        $trans = Yii::$app->db->beginTransaction();
        try {
            // 1.保存operationLog
            $this->operationLog->user_id = $this->request->getSessionUserId();
            $this->operationLog->target_user_id = $this->request->getCurrentUserId();
            $this->operationLog->exec_time = $this->getMicroTime() - $this->startMicroTime;
            if (!$this->operationLog->save()) {
                throw new Exception(
                    json_encode($this->operationLog->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            // 2.保存records
            foreach ($this->records as $record) {
                $record->operation_log_id = $this->operationLog->id;
                if (!$record->save()) {
                    throw new Exception(json_encode($record->getFirstErrors(), JSON_UNESCAPED_UNICODE));
                }
            }
            $trans->commit();
        } catch (\Exception $e) {
            $trans->rollBack();
            Yii::error('操作日志记录报错，错误信息：' . $e->getMessage(), __METHOD__);
        }

        // 记录到report
        $this->addReportLaunchLog();
        return $this;
    }

    /**
     * 取消日志
     *
     * @return $this
     */
    public function logCancel()
    {
        $this->monitorApis = [];
        return $this;
    }
}