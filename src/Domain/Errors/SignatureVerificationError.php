<?php

declare(strict_types=1);

namespace Zeny\Domain\Errors;

/** A webhook signature did not verify (`signature_verification`). */
final class SignatureVerificationError extends ZenyError {}
