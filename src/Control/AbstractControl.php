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
 * Provides controls with a shared, lazily initialised screen.
 */
class AbstractControl {
    /**
     * Screen shared by controls using this base class.
     *
     * The first screen assigned is retained for later controls.
     *
     * @var Screen
     */
    protected static Screen $staticScreen;

    /**
     * Initialises the shared screen if it hasn't been set.
     *
     * @param null|Screen $screen Optional screen to use instead of creating one.
     *
     * @return void
     */
    protected function setStaticScreen(?Screen $screen = null): void {
        // An existing screen takes precedence over any later screen supplied by a control.
        if (!isset(static::$staticScreen))
            static::$staticScreen = $screen ?? new Screen();
    }

    /**
     * Retrieves the shared screen, creating it on first access if needed.
     *
     * @return Screen The shared screen.
     */
    protected function getStaticScreen(): Screen {
        $this->setStaticScreen();

        return static::$staticScreen;
    }
}
