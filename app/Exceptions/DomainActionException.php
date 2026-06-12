<?php

namespace App\Exceptions;

use Exception;

/**
 * A domain rule rejected the requested operation.
 *
 * The message is user-facing. Web surfaces (Livewire) catch these and show
 * them in their existing error UI; the API renders them as 422 JSON (see
 * bootstrap/app.php).
 */
class DomainActionException extends Exception {}
