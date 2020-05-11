<?php

namespace toluban\MoleLog\Handler;

use yii\base\Event;
use toluban\MoleLog\CConst;
use yii\helpers\ArrayHelper;
use yii\db\BaseActiveRecord;

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
     * RecordLog constructor.
     */
    public function __construct()
    {
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_AFTER_INSERT,  [$this, 'handleAfterInsert']);
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_BEFORE_UPDATE, [$this, 'handleBeforeUpdate']);
        Event::on(BaseActiveRecord::class, BaseActiveRecord::EVENT_BEFORE_DELETE, [$this, 'handleBeforeDelete']);
    }

    /**
     * @param $record
     */
    private function pushRecords($record)
    {
        $this->records[] = $record;
    }

    /**
     * @param $table
     * @return mixed
     * @throws \yii\base\InvalidParamException
     */
    private function hasMonitorTable($table)
    {
        return ArrayHelper::getValue($this->monitorTables, 'tables.' . $table, false);
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
     * @throws \yii\base\InvalidParamException
     */
    public function handleAfterInsert($event)
    {
        $model = $this->getEventModel($event);

        // 判断api或table是否需要监控
        if(!$this->hasMonitorTable($model->tableName())) {
            return ;
        }

        $record = $this->getModel();
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
     * @throws \yii\base\InvalidParamException
     */
    public function handleBeforeDelete($event)
    {
        $model = $this->getEventModel($event);
        // 判断api或table是否需要监控
        if(!$this->hasMonitorTable($model->tableName())) {
            return ;
        }
        $record = $this->getModel();
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

        // 判断api或table是否需要监控
        if(!$this->hasMonitorTable($model->tableName())) {
            return ;
        }

        $record = $this->getModel();
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
        $monitorFields = $this->parseMonitorFields(array_keys($model->fields()), $model->tableName());
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

    /**
     * 解析监控字段
     *
     * @param $columns
     * @param $table
     * @return array
     * @throws \yii\base\InvalidParamException
     */
    private function parseMonitorFields($columns, $table)
    {
        $data = [];
        $monitorFields = ArrayHelper::getValue($this->app->params,'monitor.log.tables.'.$table);
        // 如果有字段为*，则说明要全部字段
        if (in_array('*', $monitorFields)) {
            foreach ($columns as $column) {
                $data[$column]['type'] = self::JSON_FALSE;
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
                $data[$match[1]]['type'] = self::JSON_TRUE;
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
                        $data[$column]['type'] = self::JSON_FALSE;
                    }
                }
                continue;
            }
            // 正常的
            $data[$monitorField]['type'] = self::JSON_FALSE;
        }
        return $data;
    }
}