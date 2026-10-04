<?php

namespace App\Support\Extensions;

use RuntimeException;

/** An extension's extension.json (or folder) breaks the rules of docs/EXTENSIONS_DESIGN.md. */
class InvalidExtension extends RuntimeException {}
