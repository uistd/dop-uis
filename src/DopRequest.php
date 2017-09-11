<?php

namespace FFan\Uis\Base;

/**
 * Class DopRequest
 * @package FFan\Uis\Base
 */
class DopRequest
{
    /**
     * 对象初始化
     * @param array $data
     */
    public function arrayUnpack(array $data)
    {

    }

    /**
     * 二进制解包
     * @param string $data
     * @param string|null $mask_key
     * @return bool
     */
    public function binaryDecode($data, $mask_key = null)
    {
        return true;
    }

    /**
     * 验证数据有效性
     * @return bool
     */
    public function validateCheck()
    {
        return true;
    }

    /**
     * 获取出错的消息
     * @return string|null
     */
    public function getValidateErrorMsg()
    {
        return 'ok';
    }
}
