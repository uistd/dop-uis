<?php

namespace FFan\Dop\Uis;

/**
 * Interface DopRequest
 * @package FFan\Dop\Uis
 */
interface IRequest
{
    /**
     * 对象初始化
     * @param array $data
     */
    public function arrayUnpack(array $data);

    /**
     * 转换成数组
     * @return array
     */
    public function arrayPack();

    /**
     * 验证数据有效性
     * @return bool
     */
    public function validateCheck();

    /**
     * 获取出错的消息
     * @return string|null
     */
    public function getValidateErrorMsg();
}
