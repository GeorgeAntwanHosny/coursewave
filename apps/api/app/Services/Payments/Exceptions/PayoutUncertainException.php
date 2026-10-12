<?php

namespace App\Services\Payments\Exceptions;

/** Timeout / 5xx / connection loss. Money may or may not have moved — must be reconciled, never assumed. */
class PayoutUncertainException extends PayoutProviderException {}
