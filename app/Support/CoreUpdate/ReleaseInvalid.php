<?php

namespace App\Support\CoreUpdate;

use RuntimeException;

/** A release that must not be installed; the message says why. */
class ReleaseInvalid extends RuntimeException {}
