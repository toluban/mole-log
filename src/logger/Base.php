<?php

namespace toluban\MoleLog\logger;

use yii\db\ActiveRecord;

/**
 * Class Base
 * Author: guoliuyong
 * Date: 2020-05-06 17:54
 */
class Base
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