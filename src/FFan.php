<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;
use FFan\Std\Common\Env;
use FFan\Std\Console\Debug;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogLevel;
use FFan\Std\Logger\LogRouter;
use FFan\Std\Logger\UisLogger;

/**
 * Class FFan 基础类
 * @package FFan\Dop\Uis
 */
class FFan
{
    /**
     * @var bool 日志对象
     */
    private static $is_init_logger;

    /**
     * 获取主日志对象
     * @return LogRouter
     */
    public static function getLogger()
    {
        if (!self::$is_init_logger) {
            self::$is_init_logger = true;
            self::initLogger();
        }
        return LogHelper::getLogRouter();
    }

    /**
     * 初始化日志
     */
    private static function initLogger()
    {
        $server_info = ServerHandler::getInstance();
        $app_name = $server_info->getAppName();
        $log_config = Config::get('logger');
        if (!is_array($log_config)) {
            $log_config = array('path' => 'logs', 'type' => 'file');
        }
        $env = Env::getEnv();
        $log_path = isset($log_config['path']) ? $log_config['path'] : 'logs';
        $log_path_len = strlen($log_path);
        if ('/' !== $log_path{$log_path_len - 1}) {
            $log_path .= '/';
        }
        //开发环境将所有日志，写到一个文件
        if ($env === Env::DEV) {
            $file_name = $log_path . $app_name . '/' . $app_name;
        } //其它 环境，每个page一个目录，一个action 一个文件
        else {
            $file_name = $log_path . $server_info->getPageName() . '/' . $server_info->getActionName();
        }
        //默认打开全部的日志级别
        $log_level = 0xffff;
        if ($env === Env::PRODUCT || $env === Env::UAT) {
            $log_level ^= LogLevel::DEBUG;
        }
        $err_log = $log_path . $app_name . '/error';
        $err_level = LogLevel::ERROR | LogLevel::EMERGENCY | LogLevel::ALERT | LogLevel::CRITICAL;
        //日志放远程服务器
        if (isset($log_config['host'])) {
            $main_logger = new UisLogger($file_name, $log_level, 0, $log_config);
            //错误日志
            new UisLogger($err_log, $err_level, 0, $log_config);
        } else {
            $main_logger = new FileLogger($file_name, $log_level);
            //错误日志
            new FileLogger($err_log, $err_level);
        }
        //生产环境，每一次请求一行日志
        if ($env === Env::PRODUCT) {
            $main_logger->setOption(FileLogger::OPT_BREAK_EACH_REQUEST);
        }
    }

    /**
     * 记录调试信息
     * @param mixed $var 任意变量
     */
    public static function debug($var)
    {
        $log_str = Debug::varFormat($var);
        self::getLogger()->debug('[DEBUG]' . $log_str);
    }
}
