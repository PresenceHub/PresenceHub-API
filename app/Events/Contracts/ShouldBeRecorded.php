<?php

namespace App\Events\Contracts;

/**
 * Marker interface for domain events that must be persisted to the events table.
 *
 * Listeners registered against this interface fire for any event that implements it,
 * which lets RecordEvent stay platform-agnostic and act as a single recording sink.
 */
interface ShouldBeRecorded {}
