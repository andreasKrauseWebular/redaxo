<?php

class rex_exception extends Exception
{
  public function __construct($message, $code = E_USER_ERROR, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}

class rex_sql_exception extends rex_exception
{
  public function __construct($message, $code = E_USER_ERROR, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}