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

use Exception;
use Inane\Cli\Cli;
use Inane\Console\Command\Argument;
use Inane\Console\Command\Command;
use Inane\Console\Command\Option;
use Inane\Stdlib\Array\OptionsInterface;
use ReflectionException;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_int;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use const PHP_EOL;

// ConsoleRouter.php - Main Console Router

/**
 * ConsoleRouter
 *
 * Responsible for discovering console commands via attributes on controller
 * methods, routing argv input to the correct handler, and basic parsing of
 * positional arguments and options.
 */
class ConsoleRouter {
    //#region Properties
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
    //#endregion Properties

    /**
     * @param string[] $argv command line arguments, typically from `$argv`
     */
    public function __construct(array $argv = []) {
        $this->argv = $argv;
    }

    #region Command Execution

    /**
     * Executes a registered command with the given arguments.
     * /**
     * Adds a command to the internal command registry and processes its parameters and aliases.
     *
     * @param Command           $command The command instance to be added.
     * @param \ReflectionMethod $method  A reflection method object representing the method associated with the command.
     * @param string            $class   The fully-qualified class name where the command's method is defined.
     *
     * @return void
     */
    private function addCommand(Command $command, \ReflectionMethod $method, string $class): void {
        $params = $this->parseMethodParameters($method);

        $this->commands[$command->name] = [
            'command' => $command,
            'class'   => $class,
            'method'  => $method->getName(),
            'params'  => $params,
        ];

        // Register aliases
        foreach($command->aliases as $alias) {
            $this->commands[$alias] = &$this->commands[$command->name];
        }
    }

    /**
     * Parses the parameters of the given ReflectionMethod and extracts metadata
     * for arguments or options based on defined attributes.
     *
     * @param \ReflectionMethod $method The reflection method to parse parameters from.
     *
     * @return array<int, array<string, mixed>> An array where each element represents
     *                                          metadata for an argument or option,
     *                                          including its type, name, default value,
     *                                          and description.
     */
    private function parseMethodParameters(\ReflectionMethod $method): array {
        $params = [];

        foreach($method->getParameters() as $param) {
            $argAttrs = $param->getAttributes(Argument::class);
            $optAttrs = $param->getAttributes(Option::class);

            if (!empty($argAttrs)) {
                $arg = $argAttrs[0]->newInstance();
                $params[] = [
                    'type'        => 'argument',
                    'name'        => $param->getName(),
                    'required'    => $arg->required,
                    'default'     => $arg->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),
                    'description' => $arg->description,
                ];
            } elseif (!empty($optAttrs)) {
                $opt = $optAttrs[0]->newInstance();
                $params[] = [
                    'type'        => 'option',
                    'name'        => $opt->name ?: $param->getName(),
                    'shortcut'    => $opt->shortcut,
                    'default'     => $opt->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),
                    'description' => $opt->description,
                    'valueless'   => $opt->valueless,
                ];
            }
        }

        return $params;
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
     * @param string[]                         $argv
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
                    $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-') ? $argv[++$i] : true;
                }
            } elseif (str_starts_with($iValue, '-') && $iValue !== '-') {
                // Short option
                $opt = substr($iValue, 1);
                $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-') ? $argv[++$i] : true;
            } else {
                // Positional argument
                $arguments[] = $iValue;
            }
        }

        // Build the final arguments array based on method parameters
        $result = [];
        $argIndex = 0;

        foreach($params as $param) {
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
    #endregion Command Execution

    #region Command Handlers
    /**
     * Displays help information for available commands or a specific command if provided.
     *
     * If a command name is supplied, the method outputs detailed help for that command.
     * Otherwise, it shows an overview of all available commands along with global options.
     *
     * @param string|null $commandName The name of the command for which help is requested. If null, shows a list of all commands.
     *
     * @return void
     */
    private function showHelp(?string $commandName = null): void {
        if ($commandName && isset($this->commands[$commandName])) {
            $this->showCommandHelp($commandName);

            return;
        }

        $this->output("\n\033[33m╔══════════════════════════════════════════════════════════════╗\033[0m");
        $this->output("\033[33m║\033[0m                    \033[1mPHP Console Application\033[0m                   \033[33m║\033[0m");
        $this->output("\033[33m╚══════════════════════════════════════════════════════════════╝\033[0m\n");

        $this->output("\033[1mUsage:\033[0m");
        $this->output("  php console.php <command> [arguments] [options]\n");

        $this->output("\033[1mAvailable Commands:\033[0m\n");

        // Group commands by prefix
        $grouped = $this->groupCommands();

        foreach($grouped as $group => $commands) {
            if ($group !== '_default') {
                $this->output("  \033[36m{$group}\033[0m");
            }

            foreach($commands as $cmd) {
                $aliases = !empty($cmd['command']->aliases) ? " \033[90m[" . implode('|', $cmd['command']->aliases) . "]\033[0m" : '';

                $this->output(sprintf("    \033[32m%-18s\033[0m %s%s", $cmd['command']->name, $cmd['command']->description, $aliases));
            }

            $this->output('');
        }

        $this->output("\033[1mGlobal Options:\033[0m");
        $this->output("  \033[32m-h, --help\033[0m         Display help for a command");
        $this->output("  \033[32m--version\033[0m          Display application version\n");

        $this->output("\033[90mRun 'php console.php <command> --help' for command-specific help.\033[0m\n");
    }

    /**
     * Displays the help information for a specific command, including its
     * description, usage, aliases, arguments, and options.
     *
     * @param string $commandName The name of the command for which help is to be displayed.
     *
     * @return void
     */
    private function showCommandHelp(string $commandName): void {
        $cmd = $this->commands[$commandName];

        $this->output("\n\033[1mDescription:\033[0m");
        $this->output('  ' . ($cmd['command']->description ?: 'No description available') . "\n");

        // Build usage string
        $usage = 'php console.php ' . $cmd['command']->name;
        $arguments = array_filter($cmd['params'], fn($p) => $p['type'] === 'argument');
        $options = array_filter($cmd['params'], fn($p) => $p['type'] === 'option');

        foreach($arguments as $arg) {
            $usage .= $arg['required'] ? " <{$arg['name']}>" : " [<{$arg['name']}>]";
        }

        if (!empty($options)) {
            $usage .= ' [options]';
        }

        $this->output("\033[1mUsage:\033[0m");
        $this->output("  {$usage}\n");

        // Show aliases
        if (!empty($cmd['command']->aliases)) {
            $this->output("\033[1mAliases:\033[0m");
            $this->output('  ' . implode(', ', $cmd['command']->aliases) . "\n");
        }

        // Show arguments
        if (!empty($arguments)) {
            $this->output("\033[1mArguments:\033[0m");
            foreach($arguments as $arg) {
                $required = $arg['required'] ? "\033[31m[required]\033[0m" : "\033[90m[optional]\033[0m";
                $default = !$arg['required'] && $arg['default'] !== null ? " \033[90m(default: {$arg['default']})\033[0m" : '';

                $this->output(sprintf("  \033[32m%-20s\033[0m %s %s%s", $arg['name'], $required, $arg['description'], $default));
            }
            $this->output('');
        }

        // Show options
        if (!empty($options)) {
            $this->output("\033[1mOptions:\033[0m");
            foreach($options as $opt) {
                $short = $opt['shortcut'] ? "-{$opt['shortcut']}, " : '    ';
                $name = "--{$opt['name']}";
                $type = $opt['valueless'] ? '' : '=VALUE';
                $default = $opt['default'] !== null && !$opt['valueless'] ? " \033[90m(default: {$opt['default']})\033[0m" : '';

                $this->output(sprintf("  \033[32m%s%-20s\033[0m %s%s", $short, $name . $type, $opt['description'], $default));
            }
            $this->output('');
        }
    }

    /**
     * Groups commands by their namespace.
     *
     * Commands with a colon (":") in their name are treated as belonging to a namespace,
     * with the part before the colon representing the group. Commands without a colon
     * are grouped under the "_default" namespace. Aliases are excluded from the grouping.
     *
     * Groups are sorted alphabetically by their names.
     *
     * @return array<string, array<int, array<string, mixed>>> A list of grouped commands,
     *                                                         where each key represents a group name,
     *                                                         and the value is an array of commands
     *                                                         under that group.
     */
    private function groupCommands(): array {
        $grouped = ['_default' => []];

        foreach($this->commands as $name => $cmd) {
            // Skip aliases in listing
            if ($name !== $cmd['command']->name) {
                continue;
            }

            // Check if command has namespace (contains :)
            if (str_contains($name, ':')) {
                [$group,] = explode(':', $name, 2);
                if (!isset($grouped[$group])) {
                    $grouped[$group] = [];
                }
                $grouped[$group][] = $cmd;
            } else {
                $grouped['_default'][] = $cmd;
            }
        }

        // Sort groups
        ksort($grouped);

        return $grouped;
    }
    #endregion Command Handlers

    #region Output
    /**
     * Writes a plain line to stdout.
     */
    private function output(string $message): void {
        Cli::line($message);
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
    #endregion Output

    /**
     * Registers all public methods on the given controller class that are
     * annotated with the `Command` attribute.
     *
     * @param OptionsInterface|class-string|class-string[] $controllerClasses
     *
     * @throws ReflectionException
     */
    public function registerCommands(string|OptionsInterface $controllerClasses): void {
        if (is_string($controllerClasses))
            $controllerClasses = [$controllerClasses];

        foreach($controllerClasses as $controllerClass)
            $this->register($controllerClass);
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

        foreach($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $commandAttrs = $method->getAttributes(Command::class);

            foreach($commandAttrs as $attr) {
                /** @var Command $command */
                $command = $attr->newInstance();
                $this->addCommand($command, $method, $controllerClass);
            }
        }
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

        if ($commandName === '--version') {
            $this->output('PHP Console Application v1.0.0');

            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            $this->output(PHP_EOL . "Run 'php console.php list' to see all available commands." . PHP_EOL);

            return 1;
        }

        // Check for command-specific help
        $cmdArgs = array_slice($this->argv, 2);
        if (in_array('--help', $cmdArgs) || in_array('-h', $cmdArgs)) {
            $this->showHelp($commandName);

            return 0;
        }

        $cmd = $this->commands[$commandName];

        try {
            $args = $this->parseArguments($cmdArgs, $cmd['params']);
            $controller = new $cmd['class']();
            $result = $controller->{$cmd['method']}(...$args);

            return is_int($result) ? $result : 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->output("\nRun 'php console.php {$commandName} --help' for usage information.\n");

            return 1;
        }
    }
}