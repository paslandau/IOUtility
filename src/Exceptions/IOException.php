<?php

namespace paslandau\IOUtility\Exceptions;


use Exception;

class IOException extends \Exception
{

    public function __construct($message, $code = null, $previous = null)
    {

        if ($code === null) {
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
    }
}