<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 400, 411, 413 and other 4xx without their own class, checks refused before sending (status 0), and a range download answered without 206: fix the request. */
class InvalidRequestException extends EmailValidationException
{
}
