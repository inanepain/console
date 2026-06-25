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

use Inane\Console\Control\AbstractControl;
use Inane\Console\Control\Screen;
use Inane\Stdlib\Exception\ConfigurationException;
use Inane\Stdlib\Exception\RuntimeException;

use function array_is_list;
use function count;
use function fread;

use const PHP_EOL;

/**
 * Escape sequence for moving the cursor up in the terminal.
 *
 * @var string
 */
class Select extends AbstractControl {
    #region PROPERTIES
    /**
     * Holds the current value, initialized to zero.
     */
    protected int $current = 0;

    /**
     * key
     *
     * @${CARET}
     *
     * @var false|string
     */
    private string|false $key;

    /**
     * screen
     *
     * @${CARET}
     *
     * @var Screen
     */
    protected(set) Screen $screen;

    /**
     * Stores the `SelectOption` menu items.
     */
    protected array $menuOptions = [];
    #endregion PROPERTIES

    /**
     * Constructs the class with initial items, a prompt message, configuration for return type, and an optional screen instance.
     *
     * NOTE: SelectOption
     * When specifying items as `SelectOption` objects, the `index` does not need to be unique across items.
     * This allows for a kind of item grouping with the `index` acting as a group identifier.
     * Future releases may expand on this feature, making it more intuitive.
     *
     * @param array       $items            The list of menu items. If empty an error is thrown.
     * @param string      $prompt           The prompt message to display. Defaults to 'Use ↑/↓ to navigate, Enter to select'.
     * @param null|string $menuOptionFormat An optional format string for setting the display of menu items for the select replacing indevidual items format. Defaults to null (fallback `SelectOption`).
     * @param bool        $returnIndexOnly  Indicates whether only the index of the selected item should be returned. Defaults to false which returns a `SelectOption` object.
     * @param Screen|null $screen           An optional Screen instance. If null, a new instance of Screen will be created.
     *
     * @return void
     */
    public function __construct(
        protected(set) array   $items = [] {
            get => $this->items;
            /**
             * @throws ConfigurationException If no menu items are provided.
             */ set {
                $this->current = 0;
                $this->items = $value;
                $this->populateMenuOptions();
            }
        },
        protected(set) string  $prompt = 'Use ↑/↓ to navigate, Enter to select',
        protected(set) ?string $menuOptionFormat = null,
        protected(set) bool    $returnIndexOnly = false,
        ?Screen                $screen = null,
    ) {
        $this->setStaticScreen($screen);
        $this->screen = $screen ?? $this->getStaticScreen();
    }

    /**
     * Populates the $options array with SelectOption instances created from the $items array.
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the items array is not iterable or contains invalid elements.
     * @throws ConfigurationException If no menu items are provided.
     */
    protected function populateMenuOptions(): self {
        if (empty($this->items))
            throw new ConfigurationException('Menu items may not be empty.');

        foreach($this->items as $index => $item) {
            if ($item instanceof SelectOption) {
                $this->menuOptions[] = $item;
            } else {
                if (array_is_list($this->items))
                    $this->menuOptions[] = new SelectOption($index + 1, $item);
                else
                    $this->menuOptions[] = new SelectOption($index, $item);
            }
        }

        return $this;
    }

    #region SELECT MENU

    /**
     * Renders the current prompt and list of items, highlighting the currently selected item.
     *
     * @return string The rendered output as a string.
     *
     * @throws \RuntimeException If the rendering process fails.
     */
    protected function render(): string {
        echo $this->prompt . PHP_EOL;
        $output = '';
        foreach($this->menuOptions as $index => $item) {
            $menuItem = $item->menuItem($this->menuOptionFormat);
            if ($index === $this->current) {
                $output .= "\033[7m> $menuItem\033[0m\n"; // Reverse video
            } else {
                $output .= "  $menuItem\n";
            }
        }

        return $output;
    }

    /**
     * Reads a key input from the standard input (STDIN) and stores it for further processing.
     *
     * @return void
     *
     * @throws \RuntimeException If reading from STDIN fails.
     */
    protected function readKey(): void {
        $this->key = fread(STDIN, 3);
    }

    /**
     * Handles key inputs to navigate or select items in a list.
     *
     * Updates the current position based on the key pressed (UP or DOWN)
     * or triggers actions on certain keys (NEW_LINE or CARRIAGE_RETURN).
     * Clears the screen and outputs the selected item when a selection is confirmed.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the provided key is not recognized.
     */
    protected function handleNavigation(): void {
        switch ($this->key) {
            case Screen::UP:
                $this->current = ($this->current - 1 + count($this->menuOptions)) % count($this->menuOptions);
                break;

            case Screen::DOWN:
                $this->current = ($this->current + 1) % count($this->menuOptions);
                break;

            case Screen::NEW_LINE:
            case Screen::CARRIAGE_RETURN:
                $this->screen->clear();
        }
    }

    /**
     * Handles the selection process based on key input.
     *
     * Confirms the selection if the first key matches NEW_LINE or CARRIAGE_RETURN
     * and clears the screen upon selection.
     *
     * @return bool Returns true if a selection is confirmed, false otherwise.
     *
     * @throws \RuntimeException If the screen object is not properly configured or unavailable.
     */
    protected function handleSelect(): bool {
        return $this->key[0] === Screen::NEW_LINE || $this->key[0] === Screen::CARRIAGE_RETURN;
    }

    /**
     * Retrieves the currently selected item from the list.
     *
     * Returns the item at the current position in the list of items.
     *
     * @param bool|null $returnIndexOnly Whether to return the index of the selected item only.
     *
     * @return null|bool|int|float|string|SelectOption The currently selected item.
     *
     */
    protected function selectedItem(?bool $returnIndexOnly = null): null|bool|int|float|string|SelectOption {
        if ($returnIndexOnly ?? $this->returnIndexOnly) {
            return ($this->menuOptions[$this->current])->index;
        }

        return $this->menuOptions[$this->current];
    }
    #endregion SELECT MENU

    /**
     * Displays the interactive screen for the user to navigate and select an item.
     *
     * Manages the screen rendering, input handling, and selection process in a loop
     * until an item is selected. Restores terminal settings upon completion.
     *
     * @param bool|null $returnIndexOnly Whether to return the index of the selected item only.
     *
     * @return null|bool|int|float|string|SelectOption The selected item.
     *
     * @throws RuntimeException
     */
    public function display(?bool $returnIndexOnly = null): null|bool|int|float|string|SelectOption {
        $this->screen->setSttyEcho(false)
            ->setSttyCanonical(false)
        ;
        while(true) {
            $this->screen->clear();
            echo $this->render();
            $this->readKey();
            $this->handleNavigation();
            if ($this->handleSelect()) break;
        }
        $this->screen->restoreStty();

        return $this->selectedItem($returnIndexOnly);
    }

    /**
     * Invokes the object as a callable to display the content.
     *
     * @param bool|null $returnIndexOnly Whether to return the index of the selected item only.
     *
     * @return null|bool|int|float|string|SelectOption The output generated by the display method.
     *
     * @throws RuntimeException
     */
    public function __invoke(?bool $returnIndexOnly = null): null|bool|int|float|string|SelectOption {
        return $this->display($returnIndexOnly);
    }
}
