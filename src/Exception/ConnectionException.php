<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** No HTTP answer: network failure (connection_error), attempt timeout or bulk wait deadline (timeout). */
class ConnectionException extends EmailValidationException
{
}
