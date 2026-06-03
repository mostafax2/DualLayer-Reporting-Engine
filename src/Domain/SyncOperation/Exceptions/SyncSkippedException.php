<?php

namespace Mostafax\DualLayer\Domain\SyncOperation\Exceptions;

/**
 * Thrown by SyncEngine when a lifecycle hook explicitly aborts the sync.
 * Caught in process() to mark the operation as SKIPPED rather than FAILED.
 */
final class SyncSkippedException extends \RuntimeException {}
