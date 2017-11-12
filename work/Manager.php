<?php

namespace FFan\Uis\Work;

use FFan\Std\Common\Config;
use FFan\Std\Common\InvalidConfigException;

/**
 * Class Manager
 * @package FFan\Uis\Work
 */
class Manager
{
    function __construct($conf_name)
    {
        $config_arr = Config::get('ffan-task:' . $conf_name);
        if (!is_array($config_arr)) {
            throw new InvalidConfigException('ffan-task:' . $conf_name);
        }
    }

    /**
     * grep的标志
     * @param string $file
     * @param int $pid
     * @return string
     */
    private static function grepFlag($file, $pid)
    {
        $greg_flag = 'php ' . $file . '.php';
        if (isset($GLOBALS['APP_NAME'])) {
            $greg_flag .= ' ' . $GLOBALS['APP_NAME'];
        }
        if ($pid > 0) {
            $greg_flag .= ' ' . $pid;
        }
        return $greg_flag;
    }

    /**
     * 取得该任务在运行的进程数
     * @param string $name 任务文件名
     * @param int $pid 进程ID
     * @return int
     */
    public static function processCount($name, $pid = 0)
    {
        $greg_flag = self::grepFlag($name, $pid);
        $cmd = 'ps -efww | grep "' . $greg_flag . '"|grep -v grep|wc -l';
        exec($cmd, $out);
        $exec_num = isset($out[0]) ? $out[0] : 0;
        return (int)$exec_num;
    }

    /**
     * 给某个进程传递信号
     * @param string $file 任务文件名
     * @param int $pid 子进程ID
     * @param int $signal 信号
     * @return void
     */
    function processKill($file, $pid = -1, $signal = SIGTERM)
    {
        $grep_flag = self::grepFlag($file, $pid);
        $cmd = 'ps -efww | grep "' . $grep_flag . '"|grep -v grep|awk \'{ print $2 }\'|xargs --no-run-if-empty kill -' . $signal;
        exec($cmd);
    }
}