<?php

namespace toluban\MoleLog;

use yii\base\Component;
use yii\web\Application;
use yii\base\BootstrapInterface;
use toluban\MoleLog\Handler\OperationHandler;

/**
 * Class Logger
 * Author: guoliuyong
 * Date: 2020-05-06 14:36
 */
class Logger extends Component implements BootstrapInterface
{
    /**
     * @var operationHandler
     */
    private $operationHandler;

    /**
     * @var
     */
    public $userId;

    /**
     * @var
     */
    public $targetUserId;

    /**
     * @var
     */
    public $recordModel;

    /**
     * @var
     */
    public $operationModel;

    /**
     * @var
     */
    public $monitorLog = './config/Monitor.php';

    /**
     * Logger constructor.
     * @param array $config
     * @param OperationHandler $operationHandler
     */
    public function __construct($config, OperationHandler $operationHandler)
    {
        parent::__construct($config);
        $this->operationHandler = $operationHandler;
    }

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        $this->operationHandler
            ->setApp($app)
            ->setRequest($app->getRequest())
            ->setModel($this->operationModel)
            ->setRecordModel($this->recordModel)
            ->setMonitorConfig($this->monitorLog);

        $app->on(Application::EVENT_BEFORE_REQUEST, [$this->operationHandler, 'logBegin']);
        $app->on(Application::EVENT_AFTER_REQUEST,  [$this->operationHandler, 'logEnd']);
    }
}
