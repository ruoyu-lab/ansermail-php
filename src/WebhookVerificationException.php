<?php

declare(strict_types=1);

namespace Anser;

/** A webhook push whose signature, timestamp or headers are wrong. */
class WebhookVerificationException extends \RuntimeException
{
}
