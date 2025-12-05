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

namespace Inane\Console\Router;

use Exception;
use Inane\Console\Command\Argument;
use Inane\Console\Command\Command;
use Inane\Console\Command\Option;
use ReflectionException;
use const PHP_EOL;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_int;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;

// ConsoleRouter.php - Main Console Router
/**
 * ConsoleRouter
 *
 * Responsible for discovering console commands via attributes on controller
 * methods, routing argv input to the correct handler, and basic parsing of
 * positional arguments and options.
 */
class ConsoleRouter {
    /**
     * Registered commands map.
     *
     * @var array<string, array{
     *     command: Command,
     *     class: class-string,
     *     method: string,
     *     params: array<int, array<string, mixed>>
     * }>
     */
    private array $commands = [];

    /**
     * Raw argv passed to the router.
     *
     * @var string[]
     */
    private array $argv;

    /**
     * @param string[] $argv command line arguments, typically from `$argv`
     */
    public function __construct(array $argv = []) {
        $this->argv = $argv;
    }

    /**
     * Registers all public methods on the given controller class that are
     * annotated with the `Command` attribute.
     *
     * @param class-string $controllerClass
     *
     * @throws ReflectionException
     */
    public function register(string $controllerClass): void {
        $reflection = new \ReflectionClass($controllerClass);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $commandAttrs = $method->getAttributes(Command::class);

            foreach ($commandAttrs as $attr) {
                /** @var Command $command */
                $command = $attr->newInstance();
                $this->addCommand($command, $method, $controllerClass);
            }
        }
    }

    /**
     * Adds a discovered command to the internal command map, including all
     * aliases.
     *
     * @param class-string $class
     */
    private function addCommand(Command $command, \ReflectionMethod $method, string $class): void {
        $params = $this->parseMethodParameters($method);

        $this->commands[$command->name] = [
            'command' => $command,
            'class' => $class,
            'method' => $method->getName(),
            'params' => $params
        ];

        // Register aliases
        foreach ($command->aliases as $alias) {
            $this->commands[$alias] = &$this->commands[$command->name];
        }
    }

    /**
     * Parses parameter attributes on a command method to build a parameter
     * specification for runtime argument parsing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseMethodParameters(\ReflectionMethod $method): array {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $argAttrs = $param->getAttributes(Argument::class);
            $optAttrs = $param->getAttributes(Option::class);

            if (!empty($argAttrs)) {
                $arg = $argAttrs[0]->newInstance();
                $params[] = [
                    'type' => 'argument',
                    'name' => $param->getName(),
                    'required' => $arg->required,
                    'default' => $arg->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),
                    'description' => $arg->description
                ];
            } elseif (!empty($optAttrs)) {
                $opt = $optAttrs[0]->newInstance();
                $params[] = [
                    'type' => 'option',
                    'name' => $opt->name ?: $param->getName(),
                    'shortcut' => $opt->shortcut,
                    'default' => $opt->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),
                    'description' => $opt->description,
                    'valueless' => $opt->valueless
                ];
            }
        }

        return $params;
    }

    /**
     * Executes the router using the provided argv and returns the exit code.
     *
     * @throws Exception
     */
    public function run(): int {
        if (count($this->argv) < 2) {
            $this->showHelp();
            return 0;
        }

        $commandName = $this->argv[1];

        if ($commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
            $this->showHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            return 1;
        }

        $cmd = $this->commands[$commandName];
        $args = $this->parseArguments(array_slice($this->argv, 2), $cmd['params']);

        try {
            $controller = new $cmd['class']();
            $result = $controller->{$cmd['method']}(...$args);
            return is_int($result) ? $result : 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Parses argv into a final ordered argument list based on the parameter
     * specification discovered on the command method.
     *
     * Rules:
     * - `--name=value` or `--name value` for long options
     * - `-s value` for short options
     * - remaining tokens are positional arguments
     *
     * @param string[] $argv
     * @param array<int, array<string, mixed>> $params
     *
     * @return array<int, mixed>
     */
    private function parseArguments(array $argv, array $params): array {
        $arguments = [];
        $options = [];
        $positionalIndex = 0;

        // Separate arguments and options
        foreach($argv as $i => $iValue) {
            if (str_starts_with($iValue, '--')) {
                // Long option
                $opt = substr($iValue, 2);
                if (str_contains($opt, '=')) {
                    [$key, $value] = explode('=', $opt, 2);
                    $options[$key] = $value;
                } else {
                    $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')
                        ? $argv[++$i]
                        : true;
                }
            } elseif (str_starts_with($iValue, '-') && $iValue !== '-') {
                // Short option
                $opt = substr($iValue, 1);
                $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')
                    ? $argv[++$i]
                    : true;
            } else {
                // Positional argument
                $arguments[] = $iValue;
            }
        }

        // Build the final arguments array based on method parameters
        $result = [];
        $argIndex = 0;

        foreach ($params as $param) {
            if ($param['type'] === 'argument') {
                if (isset($arguments[$argIndex])) {
                    $result[] = $arguments[$argIndex++];
                } elseif ($param['required']) {
                    throw new \RuntimeException("Required argument '{$param['name']}' is missing.");
                } else {
                    $result[] = $param['default'];
                }
            } elseif ($param['type'] === 'option') {
                $value = null;

                // Check long name
                if (isset($options[$param['name']])) {
                    $value = $options[$param['name']];
                }

                // Check shortcut
                if ($value === null && $param['shortcut'] && isset($options[$param['shortcut']])) {
                    $value = $options[$param['shortcut']];
                }

                // Use default if not provided
                if ($value === null) {
                    $value = $param['default'];
                }

                // Handle valueless flags
                if ($param['valueless'] && $value === true) {
                    $result[] = true;
                } else {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Outputs a simple command list with descriptions and aliases.
     */
    private function showHelp(): void {
        $this->info("Available commands:\n");

        foreach ($this->commands as $name => $cmd) {
            // Skip aliases in listing
            if ($name !== $cmd['command']->name) {
                continue;
            }

            $aliases = !empty($cmd['command']->aliases)
                ? ' (' . implode(', ', $cmd['command']->aliases) . ')'
                : '';

            $this->output(sprintf(
                "  %-20s %s%s",
                $cmd['command']->name,
                $cmd['command']->description,
                $aliases
            ));
        }

        $this->output('');
    }

    /**
     * Writes a plain line to stdout.
     */
    private function output(string $message): void {
        echo $message . PHP_EOL;
    }

    /**
     * Writes an info line (green) to stdout.
     */
    private function info(string $message): void {
        echo "\033[32m" . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Writes an error line (red) to stdout.
     */
    private function error(string $message): void {
        echo "\033[31mError: " . $message . "\033[0m" . PHP_EOL;
    }
}