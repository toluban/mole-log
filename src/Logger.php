<?php

namespace toluban\MoleLog;

use yii\base\Component;
use yii\web\Application;
use yii\base\BootstrapInterface;
use toluban\MoleLog\logger\OperationLog;

/**
 * Class Logger
 * Author: guoliuyong
 * Date: 2020-05-06 14:36
 */
class Logger extends Component implements BootstrapInterface
{
    /**
     * @var OperationLog
     */
    private $operationLog;

    /**
     * @var
     */
    public $operationModel;

    /**
     * @var
     */
    public $recordModel;

    /**
     * @var
     */
    public $monitorLog = './config/monitor.php';

    /**
     * Logger constructor.
     * @param array $config
     * @param OperationLog $operationLog
     */
    public function __construct($config, OperationLog $operationLog)
    {
        parent::__construct($config);
        $this->operationLog = $operationLog;
    }

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        $this->app = $app;
        $this->operationLog
            ->setApp($app)
            ->setRequest($app->getRequest())
            ->setModel($this->operationModel)
            ->setRecordModel($this->recordModel)
            ->setMonitorConfig($this->monitorLog);

        $app->on(Application::EVENT_BEFORE_REQUEST, [$this->operationLog, 'logBegin']);
        $app->on(Application::EVENT_AFTER_REQUEST,  [$this->operationLog, 'logEnd']);
    }
}
