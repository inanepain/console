<?php

/**
 * Console Screen
 *
 * Inane Library
 *
 * Provides a terminal screen and `stty` helpers used by interactive console controls.
 *
 * PHP version 8.5
 *
 * @author   Philip Michael Raab <philip@cathedral.co.za>
 * @package  inanepain\console
 * @category console
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\Console\Control;

use Inane\Cli\Cli;
use Inane\Cli\Streams;
use Inane\Stdlib\Exception\RuntimeException;

use function array_key_exists;
use function exec;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function system;
use function trim;

/**
 * Provides low-level terminal screen and `stty` state management.
 *
 * The screen helper is responsible for clearing the visible terminal area,
 * preserving/restoring terminal configuration, and parsing local terminal flags
 * for interactive console controls.
 */
class Screen {
    #region KEY CODES
    /**
     * The Beep
     *
     * @var string
     */
    protected const string BEEP = "\x07";

    /**
     * Escape sequence for moving the cursor up in the terminal.
     *
     * @var string
     */
    public const string UP = "\033[A";

    /**
     * Represents the down arrow escape sequence for terminal control.
     */
    public const string DOWN = "\033[B";

    /**
     * Represents the newline character for line breaks.
     */
    public const string NEW_LINE = "\n";

    /**
     * Represents the carriage return escape sequence, which moves the cursor to the beginning of the current line.
     */
    public const string CARRIAGE_RETURN = "\r";
    #endregion KEY CODES

    #region PROPERTIES
    /**
     * Stores the current terminal settings (`stty` state) for later restoration.
     *
     * @var string
     */
    private string $sttyState;

    /**
     * Parsed terminal flag groups keyed by `stty` section name.
     *
     * Each flag name maps to a boolean value indicating whether the terminal flag
     * is enabled (`true`) or disabled (`false`).
     *
     * @var array<string, array<string, bool>>
     */
    private array $flags;
    #endregion PROPERTIES

    /**
     * Creates a screen helper and captures the current terminal state.
     *
     * @return void
     *
     * @throws RuntimeException If the terminal settings cannot be retrieved.
     */
    public function __construct() {
        // Capture the original terminal configuration so it can be restored later.
        $this->storeStty();
        $this->parseLocalFlags();
    }

    #region UNDER DEVELOPMENT

    /**
     * Parses local terminal flags reported by `stty -a`.
     *
     * The parsed structure groups flags by the `stty` flag section. Flags prefixed
     * with `-` are considered disabled; all other flags are considered enabled.
     *
     * @return void Parsed terminal flags grouped by section.
     *
     * @throws RuntimeException If `stty -a` cannot be executed successfully.
     */
    private function parseLocalFlags(): void {
        exec('stty -a 2>/dev/null', $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('Failed to run stty');
        }

        // Extract the local flags section and normalise spacing before tokenising.
        preg_match('/(?:lflags|local flags):\s*(.*?)(?:;|$)/i', implode(' ', $output), $matches);

        $flatFlags = preg_replace('/\s+/', ' ', $matches[0])
                |> trim(...)
                |> (static fn($x) => preg_replace('/ cchars:.*$/', '', $x))
                |> (static fn($x) => explode(' ', $x));

        $this->flags = [];
        $grp = '';

        foreach($flatFlags as $flag) {
            if (str_ends_with($flag, ':')) {
                // A trailing colon indicates the start of a new flag group.
                $grp = substr($flag, 0, -1);
                $this->flags[$grp] = [];
            } elseif (str_starts_with($flag, '-')) {
                // Disabled flags are prefixed with "-".
                $this->flags[$grp][substr($flag, 1)] = false;
            } else {
                $this->flags[$grp][$flag] = true;
            }
        }
    }
    #endregion UNDER DEVELOPMENT

    /**
     * Clears the console screen by emitting the appropriate ANSI escape sequence.
     *
     * @return void
     */
    public function clear(): void {
        echo "\033[H\033[J";
    }

    /**
     * Plays a beep sound
     *
     * @param null|string $message optional text to display on beep.
     *
     * @return void
     */
    public function beep(?string $message = null): void {
        Streams::out(static::BEEP);

        if ($message !== null) Cli::line($message);
    }

    #region STTY
    #region STORE/RESTORE
    /**
     * Saves the current terminal settings (`stty` state) for later restoration.
     *
     * @return self
     */
    public function storeStty(): self {
        $this->sttyState = system('stty -g');

        return $this;
    }

    /**
     * Restores the terminal settings to their previously stored `stty` state.
     *
     * @return self
     */
    public function restoreStty(): self {
        system('stty ' . $this->sttyState);

        return $this;
    }
    #endregion STORE/RESTORE

    #region SET STATE
    /**
     * Sets the specified local flag to the given state.
     *
     * @param string $flag   The name of the local flag to set.
     * @param bool   $enable True to enable the flag, false to disable it.
     *
     * @return bool Returns true if the flag was successfully set to the specified state, false otherwise.
     *
     * @throws RuntimeException If the system call to change the flag fails or if the flag does not exist in 'lflags'.
     */
    protected function setLFlag(string $flag, bool $enable): bool {
        if (!array_key_exists($flag, $this->flags['lflags'])) {
            throw new RuntimeException("The flag '$flag' does not exist in 'lflags'.");
        }

        if ($enable && $this->flags['lflags'][$flag] === false) {
            system('stty ' . $flag);
            $this->flags['lflags'][$flag] = true;
        } else {
            system('stty -' . $flag);
            $this->flags['lflags'][$flag] = false;
        }

        return $this->flags['lflags'][$flag] === $enable;
    }

    /**
     * Restores the terminal to a sane state by executing `stty sane`.
     *
     * @return self
     */
    public function setSttySane(): self {
        system('stty sane');

        return $this;
    }

    /**
     * Enables or disables terminal echo mode.
     *
     * Echo mode controls whether the terminal displays typed characters.
     * Interactive controls commonly disable echo while reading raw key input.
     *
     * @param bool $enable Whether terminal echo should be enabled.
     *
     * @return self
     *
     * @throws RuntimeException If the system call to change the flag fails or if the flag does not exist in 'lflags'.
     */
    public function setSttyEcho(bool $enable = true): self {
        $this->setLFlag('echo', $enable);

        return $this;
    }

    /**
     * Enables or disables canonical terminal input mode.
     *
     * Canonical mode buffers input until a line delimiter is entered. Interactive
     * menus disable it to receive key presses immediately.
     *
     * @param bool $enable Whether canonical input mode should be enabled.
     *
     * @return self
     *
     * @throws RuntimeException If the system call to change the flag fails or if the flag does not exist in 'lflags'.
     */
    public function setSttyCanonical(bool $enable = true): self {
        $this->setLFlag('icanon', $enable);

        return $this;
    }
    #endregion SET STATE
    #endregion STTY
}
