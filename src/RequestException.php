<?php
/**
 *
 * User: swimtobird
 * Date: 2021-04-12
 * Email: <swimtobird@gmail.com>
 */

namespace Swimtobird\Heycar;


use Throwable;

class RequestException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}