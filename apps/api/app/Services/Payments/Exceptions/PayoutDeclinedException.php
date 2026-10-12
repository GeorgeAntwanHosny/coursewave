<?php

namespace App\Services\Payments\Exceptions;

/** The provider explicitly rejected the transfer. No money moved. Safe to mark the batch failed. */
class PayoutDeclinedException extends PayoutProviderException {}
