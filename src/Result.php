<?php

namespace FFan\Dop\Uis;

/**
 * Class Result
 * @package FFan\Dop\Uis
 */
class Result implements IResponse
{
    /**
     * @var int 状态码
     */
    public $status = 0;

    /**
     * @var string 返回消息
     */
    public $message;

    /**
     * @var mixed 数据
     */
    public $data;

    /**
     * 转成数组
     * @param bool $empty_convert 如3果数组为空，是否转成stdClass
     * @return array|object
     */
    public function arrayPack($empty_convert = false)
    {
        return array('data' => $this->data);
    }

    /**
     * 二进制打包
     * @param bool $pid 是否打包协议ID
     * @param bool $sign 是否签名
     * @param null|string $mask_key 加密字符
     * @return string
     */
    public function binaryEncode($pid = false, $sign = false, $mask_key = null)
    {
        return '';
    }
}
