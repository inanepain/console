<?php

/**
 * Inane: Console
 *
 * Console routing and argument handling.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
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
 * Represents an option that can be used as part of a command-line interface or similar functionality.
 *
 * Options are parameters that provide additional context or behavior for an operation.
 * They may have a name, an optional shortcut, a description, a default value,
 * and an indicator of whether they are valueless.
 *
 * @version 0.3.0
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class Option {
    /**
     * Constructor method to initialize the class with specific properties.
     *
     * @param string      $name        The name of the option.
     * @param string|null $shortcut    An optional shortcut for the option.
     * @param string      $description A description of the option.
     * @param mixed       $default     The default value for the option.
     * @param bool        $valueless   Indicates whether the option is valueless.
     *
     * @return void
     */
    public function __construct(
        /**
         * The name of the option.
         */
        public string $name,
        /**
         * An optional shortcut for the option.
         */
        public ?string $shortcut = null,
        /**
         * A description of the option.
         */
        public string $description = '',
        /**
         * The default value for the option.
         */
        public mixed $default = null,
        /**
         * Indicates whether the option is valueless.
         */
        public bool $valueless = false
    ) {
    }
}
