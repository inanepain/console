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
 * Represents an argument attribute that can be applied to parameters.
 *
 * This attribute holds metadata about a parameter, including its description,
 * whether it is required, and its default value.
 *
 * @param string $description A brief description of the parameter.
 * @param bool   $required    Indicates whether the parameter is mandatory. Default is true.
 * @param mixed  $default     Specifies the default value of the parameter if it is not required.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class Argument {
    /**
     * Command line argument constructor.
     *
     * @param string     $description A brief description of the parameter.
     * @param bool       $required    Indicates whether the parameter is mandatory. Default is true.
     * @param mixed|null $default     Specifies the default value of the parameter if it is not required.
     */
    public function __construct(
        /**
         * A brief description of the parameter.
         */
        public string $description = '',
        /**
         * Indicates whether the parameter is mandatory. Default is true.
         */
        public bool $required = true,
        /**
         * Specifies the default value of the parameter if it is not required.
         */
        public mixed $default = null
    ) {
    }
}