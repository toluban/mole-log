<?php

namespace toluban\MoleLog\Handler;

use yii\db\ActiveRecord;

/**
 * Class Handler
 * Author: guoliuyong
 * Date: 2020-05-11 14:19
 */
class Handler
{
    /**
     * @var
     */
    protected $model;

    /**
     * @param $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return new $this->model;
    }

    /**
     * @param $event
     * @return ActiveRecord
     */
    public function getEventModel($event)
    {
        return $event->sender;
    }
}