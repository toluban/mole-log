<?php

namespace toluban\MoleLog\Exception;

use toluban\MoleLog\CConst;
use yii\base\Exception;

/**
 * Class OperationException
 * Author: guoliuyong
 * Date: 2020-05-11 11:55
 */
class OperationException extends Exception
{
    /**
     * OperationException constructor.
     * @param $message
     * @param int $code
     */
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code ?: CConst::CODE_DEFAULT);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Operation Log Exception';
    }
}