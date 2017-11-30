<?php

namespace FFan\Uis\Work;

use FFan\Std\Common\Str;

class Task
{
    /**
     * @var CrontabConfig
     */
    private $time;

    /**
     * @var string
     */
    private $class_name;

    /**
     * @var string
     */
    private $args;

    /**
     * @var string
     */
    private $app_name;

    /**
     * Task constructor.
     * @param string $app_name
     */
    public function __construct($app_name)
    {
        $this->app_name = $app_name;
    }

    /**
     * 解析配置
     * @param string $task_config
     * @return bool
     */
    public function parse($task_config)
    {
        $tmp_arr = Str::split($task_config, ' ');
        if (count($tmp_arr) < 6) {
            return false;
        }
        //前面5项 表示 crontab
        $crontab_str = array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . array_shift($tmp_arr);
        $crontab_config = new CrontabConfig($crontab_str);
        if (!$crontab_config->isValid()) {
            return false;
        }
        //类名
        $class_name = str_replace('.php', ' ', array_shift($tmp_arr));
        //是否是合法的类名
        if (!Str::isValidClassName($class_name)) {
            return false;
        }
        $run_arg = '';
        //如果还有，剩下的就是运行参数
        if (!empty($tmp_arr)) {
            $run_arg = join(' ', $tmp_arr);
        }
        $this->class_name = $class_name;
        $this->time = $crontab_config;
        $this->args = $run_arg;
        return true;
    }

    /**
     * 是否该执行了
     * @return bool
     */
    public function isWakeUp()
    {
        return $this->time->isWakeUp();
    }

    /**
     * 获取任务类名
     * @return string
     */
    public function getClass()
    {
        return $this->class_name;
    }

    /**
     * 获取任务args
     * @return string
     */
    public function getArgs()
    {
        return $this->args;
    }
}
