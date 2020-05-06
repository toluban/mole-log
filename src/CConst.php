<?php

namespace toluban\MoleLog;

/**
 * Class CConst
 * Author: guoliuyong
 * Date: 2020-05-06 13:13
 */
class CConst
{
    /**
     * record type 1: insert  2: update  3: delete
     * @var int
     */
    const TYPE_INSERT = 1;
    const TYPE_UPDATE = 2;
    const TYPE_DELETE = 3;

    /**
     * field structure 0: not json 1: json
     * @var int
     */
    const JSON_FALSE = 0;
    const JSON_TRUE  = 1;

}