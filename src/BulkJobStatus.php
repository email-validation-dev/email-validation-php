<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `BulkJob::$status`. An unknown future value is kept as a string. */
final class BulkJobStatus
{
    public const ANALYZING = 'analyzing';
    public const READY = 'ready';
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const CANCELLING = 'cancelling';
    public const FINALIZING = 'finalizing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
}
