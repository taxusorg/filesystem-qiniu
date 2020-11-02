<?php

namespace Taxusorg\FilesystemQiniu\Exceptions;

use Throwable;

class QiniuException extends \RuntimeException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        if ($message instanceof \Qiniu\Http\Error) {
            parent::__construct($message->message(), $message->code(), $message);
        } elseif (is_string($message)) {
            parent::__construct($message, $code, $previous);
        }
    }
}
