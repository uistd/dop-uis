<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Common\Str;

/**
 * Class ActivityManager 活动管理器
 * @package FFan\Dop\Uis
 */
class ActivityManager
{
    /**
     * @var callable[]
     */
    private $activity_event = [];

    /**
     * @var Activity[]
     */
    private $instance_list;

    /**
     * @var array 生效活动配置
     */
    private $active_list;

    /**
     * @var bool 是否初化完成
     */
    private $is_init = false;

    /**
     * @var self
     */
    private static $instance;

    /**
     * Activity constructor.
     */
    public function __construct()
    {
        if (null !== self::$instance) {
            throw new \RuntimeException('ActivityManager is a singleton class');
        }
        self::$instance = $this;
    }

    /**
     * 获取所有在活动期间的活动
     * @return array
     */
    private function getActiveList()
    {
        if (null !== $this->active_list) {
            return $this->active_list;
        }
        $config_file = ROOT_PATH .'config/activity_config.php';
        $config_arr = Config::load($config_file);
        $this->instance_list = array();
        $now = time();
        foreach ($config_arr as $name => $each_conf) {
            $tmp_conf = Str::split($each_conf, ',');
            //配置格式不对
            if (!isset($tmp_conf[0], $tmp_conf[1])) {
                continue;
            }
            //判断是否在活动时间里
            $start_time = strtotime($tmp_conf[0]);
            $end_time = strtotime($tmp_conf[1]);
            if ($now < $start_time) {
                continue;
            }
            if ($end_time > 0 && $now > $end_time) {
                continue;
            }
            $this->instance_list[$name] = true;
        }
        return $this->active_list;
    }

    /**
     * 配置初始化
     */
    private function init()
    {
        if ($this->is_init) {
            return;
        }
        $this->is_init = true;
        $active_list = $this->getActiveList();
        foreach ($active_list as $class_name => $conf_name) {
            //已经初始化过了
            if (isset($this->instance_list[$class_name])) {
                continue;
            }
            $this->getActiveInstance($class_name);
        }
    }

    /**
     * 数据
     * @param string $event
     * @param mixed $data
     */
    public function trigger($event, $data)
    {
        $this->init();
        if (!isset($this->activity_event[$event])) {
            return;
        }
        foreach ($this->activity_event[$event] as $call) {
            call_user_func($call, $data);
        }
    }

    /**
     * 设置事件监听
     * @param string $event
     * @param callable $callback
     */
    public function attach($event, callable $callback)
    {
        if (!isset($this->activity_event[$event])) {
            $this->activity_event[$event] = array();
        } else {
            //检查相同回调相同事件多次设置
            foreach ($this->activity_event[$event] as $call) {
                if ($callback === $call) {
                    return;
                }
            }
        }
        $this->activity_event[$event][] = $callback;
    }

    /**
     * 获取一个实例
     * @param string $name
     * @return Activity|null
     */
    public function getActiveInstance($name)
    {
        if (!isset($this->instance_list[$name])) {
            $this->initActiveInstance($name);
        }
        return isset($this->instance_list[$name]) ? $this->instance_list[$name] : null;
    }

    /**
     * 初始化活动实例
     * @param string $name
     * @throws InvalidConfigException
     */
    private function initActiveInstance($name)
    {
        $app_name = Str::camelName(Application::getInstance()->getAppName());
        $class_name = '\\Uis\\' . $app_name . '\\Activity\\' . $name . 'Activity';
        if (!class_exists($class_name)) {
            throw new InvalidConfigException('uis-activity:' . $name . ' class not found');
        }
        $conf_file = ROOT_PATH .'config/activity/'. $name .'Config.php';
        $conf_arr = Config::load($conf_file);
        /** @var Activity $active */
        $active = new $class_name($name, $conf_arr);
        //如果 活动在生效中
        if ($this->isActive($name)) {
            //事件监听
            $active->attach();
        }
        $this->instance_list[$name] = $active;
    }

    /**
     * 某个活动是否还在继续
     * @param string $name
     * @return bool
     */
    public function isActive($name)
    {
        $list = $this->getActiveList();
        return isset($list[$name]);
    }

    /**
     * 获取实例
     * @return ActivityManager
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}