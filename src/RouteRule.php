<?php
namespace FFan\Dop\Uis;

use FFan\Std\Cache\CacheFactory;
use FFan\Std\Common\Config as FFanConfig;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Common\Str as FFanStr;
use FFan\Std\Common\Env as FFanEnv;

/**
 * Class RouteRule
 * @package ffan\php\web
 */
class RouteRule
{
    /**
     * 配置名
     */
    const CONFIG_NAME = 'route_rule';

    /**
     * @var array 规则
     */
    private $rule_arr = array();

    /**
     * 初始化
     */
    public function __construct()
    {
        $cache = CacheFactory::get('file');
        if ($cache->has(self::CONFIG_NAME)) {
            $this->rule_arr = $cache->get(self::CONFIG_NAME);
        } else {
            $conf_arr = FFanConfig::get(self::CONFIG_NAME, []);
            if (!is_array($conf_arr)) {
                throw new InvalidConfigException(self::CONFIG_NAME);
            }
            $this->addRule($conf_arr);
            //开发模式下，缓存1秒就过期，其它环境 不过期
            $ttl = FFanEnv::isDev() ? 1 : 0;
            $cache->set(self::CONFIG_NAME, $this->rule_arr, $ttl);
        }
    }

    /**
     * 加入规则
     * @param array $rules 路由规则
     * @throws InvalidConfigException
     */
    public function addRule($rules)
    {
        foreach ($rules as $pattern => $value) {
            $method = null;
            //如果中间存在空格,前面一部分就是method,后面部分是规则
            if (false !== ($pos = strpos($value, ' '))) {
                $method = strtoupper(substr($value, 0, $pos));
                $value = substr($value, $pos + 1);
            }
            self::buildRule($pattern, $value, $method);
        }
    }

    /**
     * 解析规则
     * @param string $pattern 表达式
     * @param string $route 路由
     * @param string $method
     */
    private function buildRule($pattern, $route, $method = null)
    {
        static $valid_method = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');
        $pattern = trim($pattern, ' /');
        $route = trim($route, ' /');
        if (null !== $method) {
            $tmp_method = FFanStr::split($method);
            $method = array();
            foreach ($tmp_method as $item) {
                if (in_array($valid_method, $item)) {
                    $method[$item] = true;
                }
            }
        }
        $route_params = array();
        //如果在route设置中有变量
        if (false !== strpos($route, '>') && preg_match_all('/<(\w+)>/', $route, $matches)) {
            foreach ($matches[1] as $name) {
                $route_params[$name] = '<' . $name . '>';
            }
        }
        $param_map = array();
        $pattern_tr_map = array('.' => '\.', '*' => '\*', '$' => '\$', '[' => '\[', ']' => '\]', '(' => '\(', ')' => '\)');
        $route_tr_map = array();
        if (preg_match_all('/<(\w+):?([^>]+)?>/', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1][0];
                $sub_pattern = isset($match[2][0]) ? $match[2][0] : '[^\/]+';
                $pattern_tr_map['<' . $name . '>'] = '(?P<' . $name . '>' . $sub_pattern . ')';
                if (isset($route_params[$name])) {
                    $route_tr_map[$name] = '(?P<' . $name . '>' . $sub_pattern . ')';
                } else {
                    $param_map[$name] = $sub_pattern === '[^\/]+' ? '' : '#^' . $sub_pattern . '$#u';
                }
            }
        }
        $temp = preg_replace('/<(\w+):?([^>]+)?>/', '<$1>', $pattern);
        $pattern = '#^' . trim(strtr($temp, $pattern_tr_map), '/') . '$#u';
        $route_rule = null;
        if (!empty($route_params)) {
            $route_rule = '#^' . strtr($route, $route_tr_map) . '$#u';
        }
        $rule = array(
            'method' => $method,
            'pattern' => $pattern,
            'route_rule' => $route_rule,
            'route_str' => $route,
            'route_params' => $route_params,
            'param_map' => $param_map
        );
        $this->rule_arr[] = $rule;
    }

    /**
     * 解析请求url
     * @param ServerHandler $server_info
     * @return string
     */
    public function parseRequest(ServerHandler $server_info)
    {
        $path_info = $server_info->getPathInfo();
        $method = null;
        foreach ($this->rule_arr as $each_rule) {
            if (!empty($each_rule['method'])) {
                if (null == $method) {
                    $method = $server_info->getMethod();
                }
                if (!isset($each_rule['method'][$method])) {
                    continue;
                }
            }
            $pattern = $each_rule['pattern'];
            if (!preg_match($pattern, $path_info, $matches)) {
                continue;
            }
            $convert_map = array();
            $route_params = $each_rule['route_params'];
            foreach ($matches as $name => $value) {
                if (isset($route_params[$name])) {
                    $convert_map[$route_params[$name]] = $value;
                    unset($route_params[$name]);
                } elseif (isset($each_rule['param_map'][$name])) {
                    $route_params[$name] = $value;
                }
            }
            //合并$_GET和route_params
            if (!empty($route_params)) {
                $_GET = $route_params + $_GET;
            }
            $rule_str = $each_rule['route_str'];
            if (null !== $each_rule['route_rule']) {
                $path_info = strtr($rule_str, $convert_map);
            } else {
                $path_info = $rule_str;
            }
            return $path_info;
        }
        return $path_info;
    }
}
