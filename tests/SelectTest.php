<?php

declare(strict_types = 1);

namespace Inane\Console\Tests;

use Inane\Console\Control\Screen;
use Inane\Console\Control\Select\Select;
use Inane\Console\Control\Select\SelectOption;
use Inane\Stdlib\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SelectTest extends TestCase {
    public function testConstructWithEmptyItemsThrowsConfigurationException(): void {
        $this->expectException(ConfigurationException::class);

        new TestSelect([], screen: new DummyScreen());
    }

    public function testListItemsAreMappedToOneBasedOptionIndexes(): void {
        $select = new TestSelect(['Alpha', 'Beta'], screen: new DummyScreen());

        self::assertSame(1, $select->optionAt(0)->index);
        self::assertSame(2, $select->optionAt(1)->index);
    }

    public function testAssociativeItemsKeepOriginalIndexesWhenReturningIndexOnly(): void {
        $select = new TestSelect(['create' => 'Create', 'delete' => 'Delete'], returnIndexOnly: true, screen: new DummyScreen());

        self::assertSame('create', $select->selectedItemPublic());

        $select->setCurrent(1);
        self::assertSame('delete', $select->selectedItemPublic());
    }

    public function testNavigationWrapsAroundOnUpAndDownKeys(): void {
        $select = new TestSelect(['One', 'Two', 'Three'], screen: new DummyScreen());

        $select->setKey(Screen::UP);
        $select->handleNavigationPublic();
        self::assertSame(2, $select->getCurrent());

        $select->setKey(Screen::DOWN);
        $select->handleNavigationPublic();
        self::assertSame(0, $select->getCurrent());
    }
}

final class DummyScreen extends Screen {
    public function __construct() {}
}

final class TestSelect extends Select {
    public function __construct(
        array $items,
        string $prompt = 'Use ↑/↓ to navigate, Enter to select',
        ?string $menuOptionFormat = null,
        bool $returnIndexOnly = false,
        ?Screen $screen = null,
    ) {
        parent::__construct($items, $prompt, $menuOptionFormat, $returnIndexOnly, $screen);
    }

    public function optionAt(int $index): SelectOption {
        return $this->menuOptions[$index];
    }

    public function setCurrent(int $current): void {
        $this->current = $current;
    }

    public function getCurrent(): int {
        return $this->current;
    }

    public function selectedItemPublic(?bool $returnIndexOnly = null): null|bool|int|float|string|SelectOption {
        return $this->selectedItem($returnIndexOnly);
    }

    public function handleNavigationPublic(): void {
        $this->handleNavigation();
    }

    public function setKey(string $key): void {
        $keyProperty = new ReflectionProperty(Select::class, 'key');
        $keyProperty->setValue($this, $key);
    }
}
