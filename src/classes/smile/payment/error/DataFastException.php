<?php

namespace smile\payment\error;

use Exception;

class DataFastException extends Exception
{

    public function __construct($message = "", $code = 0, $previous = NULL)
    {
        parent::__construct($message, $code, $previous);
    }

}

