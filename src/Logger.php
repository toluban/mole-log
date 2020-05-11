<?php

namespace toluban\MoleLog;

use Closure;
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
     * @var Monitor
     */
    private $monitor;

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
     * @param $config
     * @param OperationHandler $operationHandler
     * @param Monitor $monitor
     */
    public function __construct($config, OperationHandler $operationHandler, Monitor $monitor)
    {
        parent::__construct($config);
        $this->monitor          = $monitor;
        $this->operationHandler = $operationHandler;
    }

    /**
     * @param \yii\base\Application $app
     * @throws Exception\OperationException
     */
    public function bootstrap($app)
    {
        $this->monitor
            ->initParams($app)
            ->parseConfig($this->monitorLog);

        if ($this->monitor->isMonitorApi()) {
            $app->on(Application::EVENT_BEFORE_REQUEST, [$this->operationHandler, 'logBegin']);
            $app->on(Application::EVENT_AFTER_REQUEST,  [$this->operationHandler, 'logEnd']);
        }
    }

    /**
     * @return Monitor
     */
    public function getMonitor()
    {
        return $this->monitor;
    }

    /**
     * @return OperationHandler
     */
    public function getOperationHandler()
    {
        return $this->operationHandler;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        if ($this->userId instanceof Closure && is_callable($this->userId, true)) {
            return $this->userId();
        }
        return '0';
    }

    /**
     * @return string
     */
    public function getTargetUserId()
    {
        if ($this->targetUserId instanceof Closure && is_callable($this->targetUserId, true)) {
            return $this->targetUserId();
        }
        return $this->getUserId();
    }
}
