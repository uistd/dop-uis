<?php

/**
 * 打印
 */
function debug()
{
    foreach (func_get_args() as $each_arg) {
        \FFan\Dop\Uis\FFan::debug($each_arg);
    }
}

/**
 * 打印一个变更 ，并且立即结束
 * @param * $var
 */
function dd($var)
{
    \FFan\Std\Console\Debug::varFormat($var);
    die;
}

/**
 * 便捷的获取配置
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config($key, $default = null)
{
    return \FFan\Std\Common\Config::get($key, $default);
}

/**
 * print_r函数 别名
 */
function pr()
{
   call_user_func_array('print_r', func_get_args());
}
