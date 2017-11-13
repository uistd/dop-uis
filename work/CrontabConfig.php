<?php
namespace FFan\Uis\Work;
use FFan\Std\Common\Str;

/**
 * Class CrontabConfig
 * @package FFan\Uis\Work
 */
class CrontabConfig
{
    /**
     * @var array
     */
    private $conf_arr = array();

    /**
     * @var string
     */
    private $config;

    /**
     * CrontabConfig constructor.
     * @param string $config
     */
    public function __construct($config)
    {
        if (!is_string($config)) {
            $config = '';
        }
        $this->config = $config;
        $this->parse();
    }

    /**
     * 解析
     */
    private function parse()
    {
        $conf_arr = Str::split($this->config, ' ');
        if (5 !== count($conf_arr)) {
            $this->error();
        }
        //解析分钟
        $this->parseItem($conf_arr[0], 'm', 60);
        //解析小时
        $this->parseItem($conf_arr[0], 'h', 24);
        //解析天
        $this->parseItem($conf_arr[0], 'd', 31);
        //解析月
        $this->parseItem($conf_arr[0], 'M', 12);
        //解析星期
        $this->parseItem($conf_arr[0], 'w', 6);
    }

    /**
     * 解析其中某一项
     * @param string $item_value
     * @param string $type 类型
     * @param int $max_value 最大值
     */
    private function parseItem($item_value, $type, $max_value)
    {
        //如果带逗号隔开，依次处理
        if (false !== strpos($item_value, ',')) {
            $tmp_item_arr = explode(',', $item_value);
            foreach ($tmp_item_arr as $each_value) {
                $this->parseItem($each_value, $type, $max_value);
            }
            return;
        }
        $div = 1;
        //如果 带除号
        if (preg_match('#/(\d+)$#', $item_value, $split_re)) {
            $div = (int)$split_re[1];
            $item_value = str_replace('/'. $div, '', $item_value);
        }

        //表示任意
        if ('*' === $item_value) {
            if (1 === $div) {
                $this->conf_arr[$type] = true;
            } else {
                for ($v = 0; $v <= $max_value; ++$v) {
                    if (0 === $v % $div) {
                        $this->addValue($type, $v);
                    }
                }
            }
            return;
        }

        //如果是纯数字
        if (preg_match('#^\d+$#', $item_value)) {
             $value = (int)$item_value;
             if ($value > $max_value) {
                 $this->error();
             }
             if (0 === $value % $div) {
                 $this->addValue($type, $value);
             }
             return;
        }

        //如果带区间 带 区间
        if (preg_match('#^(\d+)-(\d+)$#', $item_value, $match_re)) {
            for ($i = $match_re[1]; $i <= $match_re[2]; ++$i) {
                if (0 === $i % $div) {
                    $this->addValue($type, $i);
                }
            }
            return;
        }
        //无法解析的配置
        $this->error();
    }

    /**
     * 增加值
     * @param string $type
     * @param int $value
     */
    private function addValue($type, $value)
    {
        $this->conf_arr[$type .'_'. $value] = true;
    }

    /**
     * 报错
     */
    private function error()
    {
        throw new \InvalidArgumentException('Invalid crontab config:'. $this->config);
    }
}
