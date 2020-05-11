<?php

namespace toluban\MoleLog\Handler;

use Yii;
use yii\db\Exception;
use toluban\MoleLog\Logger;
use toluban\MoleLog\Exception\OperationException;

/**
 * Class OperationHandler
 * Author: guoliuyong
 * Date: 2020-05-11 14:20
 */
class OperationHandler extends Handler
{
    /**
     * @var bool
     */
    private $goto = true;

    /**
     * @var RecordHandler
     */
    private $recordHandler;

    /**
     * @var array
     */
    private $data = [];

    /**
     * OperationHandler constructor.
     * @param RecordHandler $recordHandler
     */
    public function __construct(RecordHandler $recordHandler)
    {
        $this->recordHandler = $recordHandler;
    }

    /**
     * 开启日志
     *
     * @param $event
     * @return $this
     */
    public function logBegin($event)
    {
        $logger  = $this->getLogger($event);
        $this->setModel($logger->operationModel);

        $monitor = $logger->getMonitor();
        $this->recordHandler
            ->setModel($logger->recordModel)
            ->setMonitor($monitor)
            ->listenEvents();
        $this->data = [
            'app'               => $monitor->getAppId(),
            'route'             => $monitor->getCurrentApi(),
            'method'            => $monitor->getMethod(),
            'params'            => $monitor->getParams(),
            'route_name'        => $monitor->getApiName(),
            'type'              => $monitor->getApiType(),
            'http_request_id'   => $_SERVER['HTTP_REQUEST_ID'] ?? '--',
            'ip'                => $monitor->getRealIP()
        ];

        return $this;
    }

    /**
     * 结束日志
     *
     * @param $event
     * @return $this
     * @throws Exception
     * @throws OperationException
     */
    public function logEnd($event)
    {
        if (!$this->goto) {
            return $this;
        }
        $logger  = $this->getLogger($event);
        $monitor = $logger->getMonitor();

        $trans = Yii::$app->db->beginTransaction();
        try {
            $operationLog = $this->newModel();
            $operationLog->setAttributes(
                array_merge($this->data, [
                    'user_id'        => $logger->getUserId(),
                    'target_user_id' => $logger->getTargetUserId(),
                    'exec_time'      => $monitor->getMicroTime() - $monitor->getStartMicroTime()
                ])
            );

            if (!$operationLog->save()) {
                throw new OperationException(
                    json_encode($operationLog->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }
            foreach ($this->recordHandler->getRecords() as $record) {
                $record->operation_log_id = $operationLog->id;
                if (!$record->save()) {
                    throw new OperationException(
                        json_encode($record->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                    );
                }
            }

            $trans->commit();
        } catch (\Exception $e) {
            $trans->rollBack();
            throw new OperationException($e->getMessage());
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
        $this->goto = false;
        return $this;
    }

    /**
     * @param $event
     * @return Logger
     */
    public function getLogger($event)
    {
        return $event->sender;
    }
}