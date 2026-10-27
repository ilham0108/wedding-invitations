<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A social sign-in that cannot continue; the message is shown to the visitor.
 */
class SocialLoginException extends RuntimeException {}
