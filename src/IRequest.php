<?php

namespace UiStd\Uis\Base;

/**
 * Interface DopRequest
 * @package UiStd\Uis\Base
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
