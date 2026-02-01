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
 * @author   Philip Michael Raab<philip@cathedral.co.za>
 * @package  inanepain\console
 * @category console
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\Console\Router;

/**
 * Represents the base class for command controllers, providing a foundation
 * for handling command-based input in an application.
 *
 * This abstract class should be extended to implement specific command logic
 * and handle requests in a structured manner.
 */
abstract class AbstractCommandController implements CommandControllerInterface {}
