<?php

/**
 * Inane: Console
 *
 * Console routing and argument handling.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.4
 *
 * @author Philip Michael Raab<philip@cathedral.co.za>
 * @package inanepain\console
 * @category console
 *
 * @license UNLICENSE
 * @license https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types=1);

namespace Inane\Console\Command;

// Command.php - Command Attribute
#[\Attribute(\Attribute::TARGET_METHOD)]
class Command {
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = []
    ) {
    }
}