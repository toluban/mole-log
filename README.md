# yii框架操作日志库(moleLog)

## 概述

yii操作日志记录，根据yii事件记录，记录api调用的具有操作记录

## 设计流程

* [YII的生命周期流程图](https://www.processon.com/view/5d2ee605e4b02086110743d1)
* [操作日志的记录流程图](https://www.processon.com/view/5d300e52e4b058ef96ab9ca1)
* [AR的生命周期](https://www.processon.com/view/5d2ffafbe4b065dc42a92269#map)
* [表结构说明](https://www.processon.com/view/5d3135e9e4b0d11c8908f62b#map)

## 运行环境
- PHP 7+
- composer

## 使用方法

1. 在composer.json中，增加以下配置：（根据发布说明，选择对应的版本号。）

          "require": {
            "toluban/mole-log": "^1.0"
          }
          
          "repositories": {
              "toluban/mole-log": {
                  "type": "git",
                  "url":  "git@github.com:toluban/mole-log.git"
              }
            }

2. 执行composer install或者composer update安装依赖

3. 生成对应表结构 [查看表结构](src/Config/Init.sql)

4. 添加config配置

       'bootstrap' => [
                           [
                               'class'           => 'toluban\MoleLog\Logger',
                               'operationModel'  => 'common\daos\OperationLog',
                               'recordModel'     => 'common\daos\RecordLog',
                               'monitorLog'      => './monitor.php',
                               'userId'          => function () {
                                   return Yii::$app->getRequest()->getCurrentUserId();
                               },
                               'targetUserId'    => function () {
                                   return Yii::$app->getRequest()->getSessionUserId();
                               }
                               
                           ]
           ]


## 配置说明
* class：引入的入口文件
* operationModel：OperationLog表对应的model
* recordModel：RecordLog表对应的model
* monitorLog：需要记录日志的api和table相关配置
* userId：记录日志获取当前用户的userId的方法
* targetUserId：记录日志获取被模拟登入用户的userId的方法，没有可以不设置

## monitorLog配置格式说明

    /**
     * @desc
     *
     * api配置格式
     * xx/xx/xx：api地址   name：接口名称  type：接口类型
     *
     * tables配置格式
     * key 是表名  * 表示所有字段  *_time 表示匹配以_time结尾  time_* 表示匹配以_time开始  *_time_*中间包含_time_
     * aa.bb 表示aa结构中的bb（aa字段是个json，更多层类似）
     * 
     * [
     *    'api'    => [
     *         'xx/xx/xx'     => ['name' => 'xxx','type' => 1],
     *    ],
     *   'tables' => [
     *        'user' => ['*'],
     *        'role' => ['aa.bb.cc', '*_time', 'user_*', '*_role_*'], // 模糊匹配
     *        'test' => ['id', 'name', 'content'], // 精确匹配
     *   ]
     * ]
     *
     */
    return [
        'api'    => [
            'xx/xx/xx'     => ['name' => 'xxx','type' => 1],
        ],
        'tables' => [
            'user' => ['*'],
            'role' => ['aa.bb.cc', '*_time', 'time_*', '*_time_*']
        ]
    ];


