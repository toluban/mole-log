/******************************************/
/*   数据库全名 = --   */
/*   表名称 = operation_log   */
/******************************************/
CREATE TABLE `operation_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(64) DEFAULT '' COMMENT 'app名称',
  `user_id` int(11) DEFAULT NULL COMMENT '操作人的ID',
  `target_user_id` int(11) DEFAULT '0' COMMENT '模拟登入账户ID',
  `http_request_id` varchar(64) NOT NULL COMMENT 'http请求Id，用于日志查询',
  `type` tinyint(3) NOT NULL DEFAULT '1' COMMENT '类别,区分操作分类，1 操作站点 2 订单类  3 商品类 4 用户类',
  `route` varchar(64) DEFAULT '' COMMENT '路由api',
  `method` varchar(64) DEFAULT '' COMMENT '操作路由的方法，get post put delete 等',
  `route_name` varchar(64) DEFAULT '' COMMENT '路由别名',
  `params` text COMMENT '操作传参，json字符串',
  `ip` varchar(128) DEFAULT '' COMMENT '操作人的ip地址',
  `exec_time` bigint(20) NOT NULL DEFAULT '0' COMMENT '操作执行时间，毫妙',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_userid` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_route` (`route`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/******************************************/
/*   数据库全名 = --   */
/*   表名称 = record_log   */
/******************************************/
CREATE TABLE `record_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `operation_log_id` int(11) NOT NULL COMMENT '操作记录的id',
  `table_id` int(11) NOT NULL DEFAULT '0' COMMENT '操作表的主键id',
  `table` varchar(64) DEFAULT NULL COMMENT '操作表的表名',
  `type` tinyint(3) NOT NULL DEFAULT '1' COMMENT '操作类型 1新增 2 更新 3 删除',
  `content_before` text COMMENT '更改之前的内容 json',
  `content_after` text COMMENT '更改之后的内容 json',
  `content_change` text COMMENT '更改的差异部分 json',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_operation_log_id` (`operation_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
