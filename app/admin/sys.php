<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

use think\admin\service\AdminService;
use think\admin\service\SystemService;
use think\helper\Str;

/*! 全局操作日志数据 */
$GLOBALS['oplogs'] = [];

/*! 操作日志批量写入日志 */
app()->event->listen('HttpEnd', function () {
    if (is_array($GLOBALS['oplogs']) && count($GLOBALS['oplogs']) > 0) {
        foreach (array_chunk($GLOBALS['oplogs'], 100) as $items) {
            app()->db->name('SystemOplog')->insertAll($items);
        }
    }
});

/*! SQL 监听分析记录日志 */
app()->db->listen(function ($sqlstr) {
    [$type,] = explode(' ', $sqlstr);
    if (in_array($type, ['INSERT', 'UPDATE', 'DELETE']) && AdminService::instance()->isLogin()) {
        [$sqlstr] = explode('GROUP BY', explode('ORDER BY', $sqlstr)[0]);
        if (preg_match('/^INSERT\s+INTO\s+`(.*?)`\s+SET\s+(.*?)\s*$/i', $sqlstr, $matches)) {
            if (stripos($matches[1] = Str::studly($matches[1]), 'SystemOplog') === false) {
                $matches[2] = substr(str_replace(['`', '\''], '', $matches[2]), 0, 200);
                $GLOBALS['oplogs'][] = SystemService::instance()->getOplog("添加数据 {$matches[1]}", "添加数据：{$matches[2]}");
            }
        } elseif (preg_match('/^UPDATE\s+`(.*?)`\s+SET\s+(.*?)\s+WHERE\s+(.*?)\s*$/i', $sqlstr, $matches)) {
            if (stripos($matches[1] = Str::studly($matches[1]), 'SystemOplog') === false) {
                $matches[3] = substr(str_replace(['`', '\''], '', $matches[3]), 0, 200);
                $matches[2] = substr(str_replace(['`', '\''], '', $matches[2]), 0, 200);
                $GLOBALS['oplogs'][] = SystemService::instance()->getOplog("更新数据 {$matches[1]}（ {$matches[3]} ）", "更新内容 {$matches[2]}");
            }
        } elseif (preg_match('/^DELETE\s*FROM\s*`(.*?)`\s*WHERE\s*(.*?)\s*$/i', $sqlstr, $matches)) {
            if (stripos($matches[1] = Str::studly($matches[1]), 'SystemOplog') === false) {
                $matches[2] = str_replace(['`', '\''], '', $matches[2]);
                $GLOBALS['oplogs'][] = SystemService::instance()->getOplog("删除数据 {$matches[1]}", "删除条件 {$matches[2]}");
            }
        }
    }
});