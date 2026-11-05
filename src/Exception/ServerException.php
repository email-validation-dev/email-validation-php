<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 500, any other status of 500 or more, and any other unexpected status (such as a redirect). Nothing was charged. */
class ServerException extends EmailValidationException
{
}
