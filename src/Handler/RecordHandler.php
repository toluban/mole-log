<?php

namespace toluban\MoleLog\Handler;

use yii\base\Event;
use toluban\MoleLog\CConst;
use yii\helpers\ArrayHelper;
use yii\db\BaseActiveRecord;
use toluban\MoleLog\Monitor;

/**
 * Class RecordHandler
 * Author: guoliuyong
 * Date: 2020-05-11 14:20
 */
class RecordHandler extends Handler
{
    /**
     * @var array
     */
    private $records = [];

    /**
     * @var array
     */
    private $monitorTables = [];

    /**
     * @var Monitor
     */
    private $monitor;

    /**
     * 监听事件
     */
    public function listenEvents()
    {
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_AFTER_INSERT,  [$this, 'handleAfterInsert']);
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_BEFORE_UPDATE, [$this, 'handleBeforeUpdate']);
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_BEFORE_DELETE, [$this, 'handleBeforeDelete']);
        return $this;
    }

    /**
     * @param Monitor $monitor
     * @return $this
     */
    public function setMonitor(Monitor $monitor)
    {
        $this->monitor = $monitor;
        return $this;
    }

    /**
     * @param $record
     */
    private function pushRecords($record)
    {
        $this->records[] = $record;
    }

    /**
     * @param $tables
     */
    public function setMonitorTables($tables)
    {
        $this->monitorTables = $tables;
    }

    /**
     * @return array
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * 触发db插入事件
     *
     * @param $event
     */
    public function handleAfterInsert($event)
    {
        $model = $this->getEventModel($event);
        // 判断api或table是否需要监控
        if(!$this->monitor->isMonitorTable($model->tableName())) {
            return ;
        }

        $record = $this->newModel();
        $data = [
            'table'          => $model->tableName(),
            'table_id'       => $model->id,
            'type'           => CConst::TYPE_INSERT,
            'content_before' => null,
            'content_after'  => json_encode($model->getAttributes(), JSON_UNESCAPED_UNICODE),
            'content_change' => null
        ];
        $record->setAttributes($data);
        $this->pushRecords($record);
        return ;
    }

    /**
     * 触发db删除事件
     *
     * @param $event
     */
    public function handleBeforeDelete($event)
    {
        $model = $this->getEventModel($event);
        if(!$this->monitor->isMonitorTable($model->tableName())) {
            return ;
        }
        $record = $this->newModel();
        $data = [
            'table'             => $model->tableName(),
            'table_id'          => $model->id,
            'type'              => CConst::TYPE_DELETE,
            'content_before'    => json_encode($model->getOldAttributes(), JSON_UNESCAPED_UNICODE),
            'content_after'     => null,
            'content_change'    => null
        ];
        $record->setAttributes($data);
        $this->pushRecords($record);
        return ;
    }

    /**
     * 触发更新前事件
     *
     * @param $event
     * @throws \yii\base\InvalidParamException
     */
    public function handleBeforeUpdate($event)
    {
        $model = $this->getEventModel($event);
        if(!$this->monitor->isMonitorTable($model->tableName())) {
            return ;
        }
        $record = $this->newModel();
        $data = [
            'table'             => $model->tableName(),
            'table_id'          => $model->id,
            'type'              => CConst::TYPE_UPDATE,
            'content_before'    => $model->getOldAttributes(),
            'content_after'     => $model->getAttributes(),
            'content_change'    => []
        ];
        if (empty($model->getDirtyAttributes())) {
            return ;
        }

        // 解析监控
        $skipFields = ['modified_time', 'update_at'];
        $monitorFields = $this->monitor->parseMonitorFields(array_keys($model->fields()), $model->tableName());
        foreach ($monitorFields as $field => $monitor) {
            if (in_array($field, $skipFields)) {
                continue;
            }
            $beforeValue = ArrayHelper::getValue($data['content_before'], $field);
            $afterValue  = ArrayHelper::getValue($data['content_after'], $field);

            // isAttributeChanged 类型不同也会记录，这里再比较一下值
            if ($model->isAttributeChanged($field, false)) {
                // 1.值为json字符串的
                if ($monitor['type'] == CConst::JSON_TRUE) {
                    // 转化json
                    $beforeJson = json_decode($beforeValue, true);
                    $afterJson = json_decode($afterValue, true);
                    foreach ($monitor['keys'] as $key) {
                        $beforeVal = ArrayHelper::getValue($beforeJson, $key);
                        $afterVal  = ArrayHelper::getValue($afterJson, $key);
                        // 判断json中是否是监控key发生变化
                        if($beforeVal != $afterVal) {
                            $data['content_change'][] = [
                                'field' => $field.'.'.$key,
                                'before_value' => $beforeVal,
                                'after_value' => $afterVal
                            ];
                        }
                    }
                }
                // 2.值为非json字符串的
                if ($monitor['type'] == CConst::JSON_FALSE) {
                    $data['content_change'][] = [
                        'field' => $field,
                        'before_value' => $beforeValue,
                        'after_value' => $afterValue
                    ];
                }
            }
        }

        if(empty($data['content_change'])) {
            return ;
        }

        $data['content_change'] = json_encode($data['content_change'], JSON_UNESCAPED_UNICODE);
        $data['content_before'] = json_encode($data['content_before'], JSON_UNESCAPED_UNICODE);
        $data['content_after']  = json_encode( $data['content_after'], JSON_UNESCAPED_UNICODE);
        $record->setAttributes($data);
        $this->pushRecords($record);
        return ;
    }
}