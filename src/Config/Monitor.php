<?php
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
    ],
    'tables' => [
    ]
];