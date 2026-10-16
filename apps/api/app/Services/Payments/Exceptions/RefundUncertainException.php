<?php

namespace App\Services\Payments\Exceptions;

/** Timeout / 5xx / connection loss. The refund may or may not exist — must be reconciled, never assumed. */
class RefundUncertainException extends RefundProviderException {}
