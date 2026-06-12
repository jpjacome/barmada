<?php

namespace App\Exceptions;

/**
 * An ordered product does not resolve within the table's tenant.
 */
class InvalidProductException extends DomainActionException {}
