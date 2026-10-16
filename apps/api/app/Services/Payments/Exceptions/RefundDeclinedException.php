<?php

namespace App\Services\Payments\Exceptions;

/** The provider explicitly rejected the refund. No money moved. */
class RefundDeclinedException extends RefundProviderException {}
