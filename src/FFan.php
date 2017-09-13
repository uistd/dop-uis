<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;
use FFan\Std\Common\Env;
use FFan\Std\Common\Utils;
use FFan\Std\Console\Debug;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogLevel;
use FFan\Std\Logger\LogRouter;

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
        $config_path = Config::getString('log_path', 'logs');
        $app_log_path = Utils::fixWithRuntimePath($config_path) . $app_name . '/';
        $env = Env::getEnv();
        //开发环境将所有日志，写到一个文件
        if ($env === Env::DEV) {
            $file_name = $app_name;
            $log_path = $app_log_path;
        } //其它 环境，每个page一个目录，一个action 一个文件
        else {
            $log_path = $app_log_path . $server_info->getPageName() . '/';
            $file_name = $server_info->getActionName();
        }
        //默认打开全部的日志级别
        $log_level = 0xffff;
        if ($env === Env::PRODUCT || $env === Env::UAT) {
            $log_level ^= LogLevel::DEBUG;
        }
        $main_logger = new FileLogger($log_path, $file_name, $log_level);
        //生产环境，每一次请求一行日志
        if ($env === Env::PRODUCT) {
            $main_logger->setOption(FileLogger::OPT_BREAK_EACH_REQUEST);
        }
        new FileLogger($app_log_path, 'error', LogLevel::ERROR | LogLevel::EMERGENCY | LogLevel::ALERT | LogLevel::CRITICAL);
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
