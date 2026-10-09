<?php

namespace App\Support\Deploy;

use RuntimeException;

/** Why content was not exported or imported; the message starts with a stable reason code. */
class ContentTransferRefused extends RuntimeException {}
