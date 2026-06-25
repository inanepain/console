<?php

/**
 * Select
 *
 * Inane Library
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
 *
 * @author   Philip Michael Raab <philip@cathedral.co.za>
 * @package  inanepain\select
 * @category select
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\Console\Control\Select;

use Stringable;

use function str_replace;

/**
 * Represents one selectable menu option for the console select control.
 *
 * The option keeps the value returned by the menu (`$index`), the text displayed
 * to the user (`$label`), and the default menu rendering format.
 */
class SelectOption implements Stringable {
    /**
     * Creates a selectable menu option.
     *
     * The format string supports the following placeholders:
     * - `{l}`: replaced with the option label.
     * - `{i}`: replaced with the option index/value.
     *
     * @param null|bool|int|float|string $index      Value returned when this option is selected (and `$returnIndexOnly` on `Select` set to `true`).
     * @param string                     $label      Human-readable option label.
     * @param string                     $menuFormat Default display format for this option (`$menuOptionFormat` on the `Select` object take precedence over this one).
     *
     * @return void
     */
    public function __construct(
        protected(set) null|bool|int|float|string $index,
        protected(set) string                     $label,
        protected(set) string                     $menuFormat = '{l}',
    ) {
        // Property promotion performs all option initialisation.
    }

    /**
     * Returns the option label for string contexts.
     *
     * @return string The human-readable option label.
     */
    public function __toString(): string {
        return $this->label;
    }

    /**
     * Builds the rendered menu item text for this option.
     *
     * Uses the supplied format when provided; otherwise, it falls back to the
     * option's configured default format.
     *
     * @param null|string $format Optional format overriding the option default.
     *
     * @return string The formatted menu item label.
     */
    public function menuItem(?string $format = null): string {
        // Replace supported placeholders with the option's values.
        return str_replace([
            '{l}',
            '{i}',
        ], [
            $this->label,
            $this->index,
        ], $format ?? $this->menuFormat);
    }
}
