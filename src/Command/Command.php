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

/**
 * Represents a command that can be executed, with a name, description,
 * and optional aliases.
 *
 * Attributes:
 * - name: The name of the command.
 * - description: A brief description of the command's purpose.
 * - aliases: Alternative names for the command.
 *
 * @version 0.3.0
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Command {
    /**
     * Constructor method for initialising a console command with a name, description, and aliases.
     *
     * @param string $name        The name of the command.
     * @param string $description A brief description of the command's purpose (optional).
     * @param array  $aliases     An array of alternative names or aliases (optional).
     *
     * @return void
     */
    public function __construct(
        /**
         * The name of the command.
         */
        public string $name,
        /**
         * A brief description of the command's purpose.
         */
        public string $description = '',
        /**
         * Alternative names for the command.
         */
        public array $aliases = []
    ) {
    }
}
