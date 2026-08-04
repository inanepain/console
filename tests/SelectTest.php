<?php

declare(strict_types=1);

namespace Inane\Console\Tests;

use Inane\Console\Control\Screen;
use Inane\Console\Control\Select\Select;
use Inane\Console\Control\Select\SelectOption;
use Inane\Stdlib\Exception\ConfigurationException;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;

/**
 * Verifies select menu option creation, formatting, selection, and navigation behavior.
 */
final class SelectTest extends TestCase {
    /**
     * Ensures an empty item list is rejected during select construction.
     */
    public function testConstructWithEmptyItemsThrowsConfigurationException(): void {
        $this->expectException(ConfigurationException::class);

        // Empty menus are invalid because the control cannot render or select any options.
        new TestSelect([], screen: new DummyScreen());
    }

    /**
     * Ensures sequential list items receive one-based indexes for display and selection.
     */
    public function testListItemsAreMappedToOneBasedOptionIndexes(): void {
        $select = new TestSelect([
            'Alpha',
            'Beta',
        ], screen: new DummyScreen());

        // Plain list items are converted into SelectOption instances with one-based indexes.
        self::assertSame(1, $select->optionAt(0)->index);
        self::assertSame(2, $select->optionAt(1)->index);
    }

    /**
     * Ensures associative array keys are preserved when only the selected index is returned.
     */
    public function testAssociativeItemsKeepOriginalIndexesWhenReturningIndexOnly(): void {
        $select = new TestSelect([
            'create' => 'Create',
            'delete' => 'Delete',
        ], returnIndexOnly: true, screen: new DummyScreen());

        // The first selected item should expose its original associative key.
        self::assertSame('create', $select->selectedItemPublic());

        // Moving the cursor should return the next option's original associative key.
        $select->setCurrent(1);
        self::assertSame('delete', $select->selectedItemPublic());
    }

    /**
     * Ensures supplied SelectOption instances are reused and rendered with the select override format.
     */
    public function testCustomOptionIsRetainedAndUsesTheSelectFormat(): void {
        $option = new SelectOption('create', 'Create', '{l}');
        $select = new TestSelect([$option], menuOptionFormat: '[{i}] {l}', screen: new DummyScreen());

        // Custom options should not be replaced while populating menu options.
        self::assertSame($option, $select->selectedItemPublic());
        self::assertSame('[create] Create', $select->optionAt(0)->menuItem('[{i}] {l}'));
    }

    /**
     * Ensures an option falls back to its own format when no render override is supplied.
     */
    public function testOptionUsesItsDefaultFormatWhenNoOverrideIsProvided(): void {
        $option = new SelectOption(3, 'Deploy', '{i}: {l}');

        // Casting uses the label, while menu rendering uses the option's configured format.
        self::assertSame('Deploy', (string) $option);
        self::assertSame('3: Deploy', $option->menuItem());
    }

    /**
     * Ensures keyboard navigation wraps from the first option to the last and back again.
     */
    public function testNavigationWrapsAroundOnUpAndDownKeys(): void {
        $select = new TestSelect([
            'One',
            'Two',
            'Three',
        ], screen: new DummyScreen());

        // Pressing UP on the first item wraps to the final option.
        $select->setKey(Screen::UP);
        $select->handleNavigationPublic();
        self::assertSame(2, $select->getCurrent());

        // Pressing DOWN on the final item wraps back to the first option.
        $select->setKey(Screen::DOWN);
        $select->handleNavigationPublic();
        self::assertSame(0, $select->getCurrent());
    }
}

/**
 * Represents a dummy screen in the application.
 *
 * This class extends the base Screen class and provides a basic implementation
 * for a dummy screen. It does not include any specific functionality beyond the
 * default constructor provided by the parent class.
 *
 * @throws InvalidArgumentException if an invalid argument is passed during construction.
 */
final class DummyScreen extends Screen {
    /**
     * Initializes a new instance of the class.
     *
     * This constructor sets up any necessary initial configurations or states for the class.
     *
     * @return void
     */
    public function __construct() {}
}

/**
 * TestSelect class extends Select and provides additional functionality for selecting items from a menu.
 *
 * @param array   $items            Array of items to display in the menu.
 * @param string  $prompt           Prompt message to display to the user.
 * @param ?string $menuOptionFormat Optional format for menu options.
 * @param bool    $returnIndexOnly  Flag to determine if only the index of the selected item should be returned.
 * @param ?Screen $screen           Optional Screen object to use for rendering the menu.
 */
final class TestSelect extends Select {
    /**
     * Constructs a new instance of the class.
     *
     * @param array   $items            The items to be displayed in the menu.
     * @param string  $prompt           The prompt message for user interaction.
     * @param ?string $menuOptionFormat The format for displaying menu options.
     * @param bool    $returnIndexOnly  Indicates whether to return only the index of the selected item.
     * @param ?Screen $screen           The screen object to be used for rendering.
     *
     * @return void
     */
    public function __construct(
        array   $items,
        string  $prompt = 'Use ↑/↓ to navigate, Enter to select',
        ?string $menuOptionFormat = null,
        bool    $returnIndexOnly = false,
        ?Screen $screen = null,
    ) {
        parent::__construct($items, $prompt, $menuOptionFormat, $returnIndexOnly, $screen);
    }

    /**
     * Retrieves the select option at the specified index.
     *
     * @param int $index The index of the option to retrieve.
     *
     * @return SelectOption The select option at the given index.
     *
     * @throws OutOfBoundsException if the index is out of range.
     */
    public function optionAt(int $index): SelectOption {
        return $this->menuOptions[$index];
    }

    /**
     * Sets the current index.
     *
     * @param int $current The new current index to be set.
     *
     * @return void
     */
    public function setCurrent(int $current): void {
        $this->current = $current;
    }

    /**
     * Retrieves the current value.
     *
     * @return int The current value.
     */
    public function getCurrent(): int {
        return $this->current;
    }

    /**
     * Retrieves the selected item from the menu.
     *
     * @param ?bool $returnIndexOnly If true, returns only the index of the selected item; otherwise, returns the item itself.
     *
     * @return null|bool|int|float|string|SelectOption The selected item based on the returnIndexOnly parameter.
     */
    public function selectedItemPublic(?bool $returnIndexOnly = null): null|bool|int|float|string|SelectOption {
        return $this->selectedItem($returnIndexOnly);
    }

    /**
     * Handles public navigation logic.
     *
     * This method delegates the navigation handling to the private method `handleNavigation`.
     *
     * @return void
     */
    public function handleNavigationPublic(): void {
        $this->handleNavigation();
    }

    /**
     * Sets the key for the Select instance.
     *
     * @param string $key The key to be set.
     *
     * @return void
     *
     * @throws ReflectionException If the property does not exist or is inaccessible.
     */
    public function setKey(string $key): void {
        $keyProperty = new ReflectionProperty(Select::class, 'key');
        $keyProperty->setValue($this, $key);
    }
}
