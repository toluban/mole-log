<?php

namespace toluban\MoleLog\Handler;

use Yii;
use yii\helpers\BaseJson;
use yii\web\Application;
use yii\web\Request;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

/**
 * Class OperationHandler
 * Author: guoliuyong
 * Date: 2020-05-11 14:20
 */
class OperationHandler extends Handler
{
    /**
     * @var RecordHandler
     */
    private $recordHandler;

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
     * OperationHandler constructor.
     * @param RecordHandler $recordHandler
     */
    public function __construct(RecordHandler $recordHandler)
    {
        $this->recordHandler = $recordHandler;
    }

    /**
     * @return array
     */
    private function parseRequest()
    {
        $params = $this->request->get();
        if ($this->request->isPost || $this->request->isPut || $this->request->isDelete) {
            $params = array_merge($params, $this->request->bodyParams);
        }
        return ['method' => $this->request->method, 'params' => BaseJson::encode($params)];
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
     * @param $app
     * @return $this
     */
    public function setApp($app)
    {
        $this->app = $app;
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
     * @param $monitorConfig
     * @return $this
     */
    public function setMonitorConfig($monitorConfig)
    {
        $monitorConfig     = require($monitorConfig);
        $this->monitorApis = $monitorConfig['api'] ?? [];
        $this->recordHandler->setMonitorTables($monitorConfig['tables'] ?? []);
        return $this;
    }

    /**
     * @param $model
     * @return $this
     */
    public function setRecordModel($model)
    {
        $this->recordHandler->setModel($model);
        return $this;
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
            'exec_time'         => $this->getMicroTime()
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
            $this->model->user_id        = $this->request->getSessionUserId();
            $this->model->target_user_id = $this->request->getCurrentUserId();
            $this->model->exec_time      = $this->getMicroTime() - $this->model->exec_time;

            if (!$this->model->save()) {
                throw new Exception(
                    json_encode($this->model->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            // 2.保存records
            foreach ($this->recordHandler->getRecords() as $record) {
                $record->operation_log_id = $this->model->id;
                if (!$record->save()) {
                    throw new Exception(json_encode($record->getFirstErrors(), JSON_UNESCAPED_UNICODE));
                }
            }

            $trans->commit();
        } catch (\Exception $e) {
            $trans->rollBack();
            Yii::error('操作日志记录报错，错误信息：' . $e->getMessage(), __METHOD__);
        }

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