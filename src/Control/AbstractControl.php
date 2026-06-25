<?php

/**
 * AbstractControl
 *
 * Inane Library
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
 *
 * @author   Philip Michael Raab <philip@cathedral.co.za>
 * @package  inanepain\abstract-control
 * @category abstract-control
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\Console\Control;

/**
 * AbstractControl is a base class providing functionality to manage
 * a shared static instance of a Screen object. It defines methods
 * to initialize and retrieve this static instance.
 */
class AbstractControl {
    /**
     * The static screen instance.
     *
     * NOTE: Can only be set once.
     *
     * @var Screen
     */
    protected static Screen $staticScreen;

    /**
     * Sets the static screen instance if it has not been set already.
     *
     * NOTE: Can only be set once.
     *
     * @param Screen|null $screen The screen instance to be set. If null, a new Screen instance will be created and set.
     *
     * @return void
     */
    protected function setStaticScreen(?Screen $screen = null): void {
        if (!isset(static::$staticScreen))
            static::$staticScreen = $screen ?? new Screen();
    }

    /**
     * Retrieves the static screen instance.
     *
     * NOTE: If not set, a new Screen instance will be created and set.
     *
     * @return Screen The static screen instance.
     */
    protected function getStaticScreen(): Screen {
        $this->setStaticScreen();

        return static::$staticScreen;
    }
}
