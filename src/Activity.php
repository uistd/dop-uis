<?php

namespace UiStd\Uis\Base;

use UiStd\Common\ConfigBase;

/**
 * Class Activity
 * @package UiStd\Uis\Base
 */
abstract class Activity extends ConfigBase
{
    /**
     * @var string
     */
    private $name;

    /**
     * Activity constructor.
     * @param string $name
     * @param array $conf 配置
     */
    public function __construct($name, $conf)
    {
        $this->name = $name;
        $this->initConfig($conf);
    }

    /**
     * 设置事件监听
     */
    abstract public function attach();

    /**
     * 获取实例
     * @return static|null
     */
    public static function getInstance()
    {
        $class = static::class;
        $pos = strrpos($class, '\\');
        if (false !== $pos) {
            $class = substr($class, $pos + 1);
        }
        //移除类名后缀
        $class = str_replace('Activity','', $class);
        return ActivityManager::getInstance()->getActiveInstance($class);
    }

    /**
     * 是否在活动期间
     */
    public function isActive()
    {
        return ActivityManager::getInstance()->isActive($this->name);
    }
}