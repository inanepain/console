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
use Inane\Cli\{
    Cli,
    Pencil,
    Pencil\Colour};
use Inane\Console\Command\{
    Argument,
    Command,
    Option};
use Inane\Stdlib\Array\OptionsInterface;
use Inane\Stdlib\Exception\RuntimeException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use function array_merge;
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

/**
 * ConsoleRouter
 *
 * Responsible for discovering console commands via attributes on controller
 * methods, routing argv input to the correct handler, and basic parsing of
 * positional arguments and options.
 *
 * @version 0.2.0
 */
class ConsoleRouter {
    //#region Properties
    /**
     * Registered commands map.
     *
     * @var array<string, array{
     *     command: Command,   // Represents a command that can be executed, with a name, description,
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
     * Path to the executable to be run.
     *
     * @var string
     */
    private string $executable;

    private static Pencil $error;   // Pencil: Output assigned a colour and style.
    //#endregion Properties

    /**
     * Constructs a new ConsoleRouter instance.
     *
     * @param string[] $argv command line arguments, typically from `$argv`
     */
    public function __construct(array $argv = []) {
        $this->argv = $argv;   // Raw argv passed to the router. | Constructs a new ConsoleRouter instance.
        $this->executable = $argv[0] ?? '';   // Path to the executable to be run. | Constructs a new ConsoleRouter instance.

        if (!isset(static::$error))   // <p>Determine if a variable is set and is not <b>NULL</b>.</p>
            static::$error = new Pencil(Colour::Red);   // Pencil constructor
    }

    #region Command Execution

    /**
     * Executes a registered command with the given arguments.
     * /**
     * Adds a command to the internal command registry and processes its parameters and aliases.
     *
     * @param Command           $command The command instance to be added.   // Represents a command that can be executed, with a name, description.   // Represents a command that can be executed, with a name, description,
     * @param ReflectionMethod $method  A reflection method object representing the method associated with the command.   // The <b>ReflectionMethod</b> class reports
     * @param string            $class   The fully qualified class name where the command's method is defined.
     *
     * @return void
     */
    private function addCommand(Command $command, ReflectionMethod $method, string $class): void {   // Represents a command that can be executed, with a name, description, | The <b>ReflectionMethod</b> class reports
        $params = $this->parseMethodParameters($method);   // Parses the parameters of the given ReflectionMethod and extracts metadata

        $this->commands[$command->name] = [   // Registered commands map. | The name of the command.
            'command' => $command,   // Executes a registered command with the given arguments.
            'class'   => $class,   // Executes a registered command with the given arguments.
            'method'  => $method->getName(),   // Gets function name
            'params'  => $params,
        ];

        // Register aliases
        foreach($command->aliases as $alias) {   // Alternative names for the command.
            $this->commands[$alias] = &$this->commands[$command->name];   // Registered commands map. | The name of the command.
        }
    }

    /**
     * Parses the parameters of the given ReflectionMethod and extracts metadata
     * for arguments or options based on defined attributes.
     *
     * @param ReflectionMethod $method The reflection method to parse parameters from.   // The <b>ReflectionMethod</b> class reports
     *
     * @return array<int, array<string, mixed>> An array where each element represents
     *                                          metadata for an argument or option,
     *                                          including its type, name, default value,
     *                                          and description.
     */
    private function parseMethodParameters(ReflectionMethod $method): array {   // The <b>ReflectionMethod</b> class reports
        $params = [];

        foreach($method->getParameters() as $param) {   // Gets parameters
            $argAttrs = $param->getAttributes(Argument::class);   // @template T
            $optAttrs = $param->getAttributes(Option::class);   // @template T

            if (!empty($argAttrs)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
                $arg = $argAttrs[0]->newInstance();   // Creates a new instance of the attribute with passed arguments
                $params[] = [
                    'type'        => 'argument',
                    'name'        => $param->getName(),   // Gets parameter name
                    'required'    => $arg->required,
                    'default'     => $arg->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),   // Checks if a default value is available | Gets default parameter value
                    'description' => $arg->description,
                ];
            } elseif (!empty($optAttrs)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
                $opt = $optAttrs[0]->newInstance();   // Creates a new instance of the attribute with passed arguments
                $params[] = [
                    'type'        => 'option',
                    'name'        => $opt->name ?: $param->getName(),   // Gets parameter name
                    'shortcut'    => $opt->shortcut,
                    'default'     => $opt->default ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null),   // Checks if a default value is available | Gets default parameter value
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
     * @since 0.2.0 Handles variadic arguments.
     *
     * @param string[]                         $argv
     * @param array<int, array<string, mixed>> $params
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException   // Exception thrown if an error which can only be found on runtime occurs.
     */
    private function parseArguments(array $argv, array $params): array {
        $arguments = [];
        $options = [];

        // Separate arguments and options
        foreach($argv as $i => $iValue) {   // Parses argv into a final ordered argument list based on the parameter
            if (str_starts_with($iValue, '--')) {   // The function returns {@see true} if the passed $haystack starts from the
                // Long option
                $opt = substr($iValue, 2);   // Return part of a string or false on failure. For PHP8.0+ only string is returned
                if (str_contains($opt, '=')) {   // Checks if $needle is found in $haystack and returns a boolean value
                    [$key, $value] = explode('=', $opt, 2);   // Split a string by a string
                    $options[$key] = $value;
                } else {
                    $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-') ? $argv[++$i] : true;   // The function returns {@see true} if the passed $haystack starts from the | Parses argv into a final ordered argument list based on the parameter
                }
            } elseif ($iValue !== '-' && str_starts_with($iValue, '-')) {   // The function returns {@see true} if the passed $haystack starts from the
                // Short option
                $opt = substr($iValue, 1);   // Return part of a string or false on failure. For PHP8.0+ only string is returned
                $options[$opt] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-') ? $argv[++$i] : true;   // The function returns {@see true} if the passed $haystack starts from the | Parses argv into a final ordered argument list based on the parameter
            } else {
                // Positional argument
                $arguments[] = $iValue;
            }
        }

        // Build the final arguments array based on method parameters
        $result = [];
        $argIndex = 0;

        foreach($params as $param) {   // Parses argv into a final ordered argument list based on the parameter
            if ($param['type'] === 'argument') {
                if (isset($arguments[$argIndex])) {   // <p>Determine if a variable is set and is not <b>NULL</b>.</p>
                    $result[] = $arguments[$argIndex++];

                    // Handle variadic arguments if it is the last argument.
                    if (count($arguments) > $argIndex) {   // Counts all elements in an array, or something in an object.
                        $result = array_merge($result, array_slice($arguments, $argIndex));   // Merges the elements of one or more arrays together (if the input arrays have the same string keys, then the later value for that key will overwrite the previous one; if the arrays contain numeric keys, the later value will be appended)
                    }
                } elseif ($param['required']) {
                    throw new RuntimeException("Required argument '{$param['name']}' is missing.");   // Construct the exception. Note: The message is NOT binary safe.   // Custom construct template
                } else {
                    $result[] = $param['default'];
                }
            } elseif ($param['type'] === 'option') {
                $value = $options[$param['name']] ?? null;

                // Check shortcut
                if ($value === null && $param['shortcut'] && isset($options[$param['shortcut']])) {   // <p>Determine if a variable is set and is not <b>NULL</b>.</p>
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
        if ($commandName && isset($this->commands[$commandName])) {   // Displays help information for available commands or a specific command if provided. | <p>Determine if a variable is set and is not <b>NULL</b>.</p>
            $this->showCommandHelp($commandName);   // Displays the help information for a specific command, including its

            return;
        }

        $this->output("\n\033[33m╔══════════════════════════════════════════════════════════════╗\033[0m");   // Writes a plain line to stdout.
        $this->output("\033[33m║\033[0m                    \033[1mPHP Console Application\033[0m                   \033[33m║\033[0m");   // Writes a plain line to stdout.
        $this->output("\033[33m╚══════════════════════════════════════════════════════════════╝\033[0m\n");   // Writes a plain line to stdout.

        $this->output("\033[1mUsage:\033[0m");                                    // Writes a plain line to stdout.
        $this->output("  $this->executable <command> [arguments] [options]\n");   // Writes a plain line to stdout.

        $this->output("\033[1mAvailable Commands:\033[0m\n");   // Writes a plain line to stdout.

        // Group commands by prefix
        $grouped = $this->groupCommands();   // Groups commands by their namespace.

        foreach($grouped as $group => $commands) {
            if ($group !== '_default') {
                $this->output("  \033[36m$group\033[0m");   // Writes a plain line to stdout.
            }

            foreach($commands as $cmd) {
                $aliases = !empty($cmd['command']->aliases) ? " \033[90m[" . implode('|', $cmd['command']->aliases) . "]\033[0m" : '';   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value | Join array elements with a string

                $this->output(sprintf("    \033[32m%-18s\033[0m %s%s", $cmd['command']->name, $cmd['command']->description, $aliases));   // Writes a plain line to stdout.
            }

            $this->output('');   // Writes a plain line to stdout.
        }

        $this->output("\033[1mGlobal Options:\033[0m");   // Writes a plain line to stdout.
        $this->output("  \033[32m-h, --help\033[0m         Display help for a command");   // Writes a plain line to stdout.
        $this->output("  \033[32m--version\033[0m          Display application version" . PHP_EOL);   // Writes a plain line to stdout.

        $this->output("\033[90mRun '$this->executable <command> --help' for command-specific help.\033[0m\n");   // Writes a plain line to stdout.
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
        $cmd = $this->commands[$commandName];   // Registered commands map. | Displays the help information for a specific command, including its

        $this->output("\n\033[1mDescription:\033[0m");                                                      // Writes a plain line to stdout.
        $this->output('  ' . ($cmd['command']->description ?: 'No description available') . "\n");          // Writes a plain line to stdout.

        // Build usage string
        $usage = $this->executable . ' ' . $cmd['command']->name;                                           // Path to the executable to be run.
        $arguments = array_filter($cmd['params'], static fn($p) => $p['type'] === 'argument');              // Iterates over each value in the <b>array</b>
        $options = array_filter($cmd['params'], static fn($p) => $p['type'] === 'option');                  // Iterates over each value in the <b>array</b>

        foreach($arguments as $arg) {
            $usage .= $arg['required'] ? " <{$arg['name']}>" : " [<{$arg['name']}>]";
        }

        if (!empty($options)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
            $usage .= ' [options]';
        }

        $this->output("\033[1mUsage:\033[0m");   // Writes a plain line to stdout.
        $this->output("  $usage\n");             // Writes a plain line to stdout.

        // Show aliases
        if (!empty($cmd['command']->aliases)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
            $this->output("\033[1mAliases:\033[0m");   // Writes a plain line to stdout.
            $this->output('  ' . implode(', ', $cmd['command']->aliases) . "\n");   // Writes a plain line to stdout.
        }

        // Show arguments
        if (!empty($arguments)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
            $this->output("\033[1mArguments:\033[0m");   // Writes a plain line to stdout.
            foreach($arguments as $arg) {
                $required = $arg['required'] ? "\033[31m[required]\033[0m" : "\033[90m[optional]\033[0m";
                $default = !$arg['required'] && $arg['default'] !== null ? " \033[90m(default: {$arg['default']})\033[0m" : '';

                $this->output(sprintf("  \033[32m%-20s\033[0m %s %s%s", $arg['name'], $required, $arg['description'], $default));   // Writes a plain line to stdout.
            }
            $this->output('');   // Writes a plain line to stdout.
        }

        // Show options
        if (!empty($options)) {   // Determine whether a variable is considered to be empty. A variable is considered empty if it does not exist or if its value
            $this->output("\033[1mOptions:\033[0m");   // Writes a plain line to stdout.
            foreach($options as $opt) {
                $short = $opt['shortcut'] ? "-{$opt['shortcut']}, " : '    ';
                $name = "--{$opt['name']}";
                $type = $opt['valueless'] ? '' : '=VALUE';
                $default = $opt['default'] !== null && !$opt['valueless'] ? " \033[90m(default: {$opt['default']})\033[0m" : '';

                $this->output(sprintf("  \033[32m%s%-20s\033[0m %s%s", $short, $name . $type, $opt['description'], $default));   // Writes a plain line to stdout.
            }
            $this->output('');   // Writes a plain line to stdout.
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

        foreach($this->commands as $name => $cmd) {   // Registered commands map.
            // Skip aliases in listing
            if ($name !== $cmd['command']->name) {   // The name of the command.
                continue;
            }

            // Check if command has namespace (contains :)
            if (str_contains($name, ':')) {   // Checks if $needle is found in $haystack and returns a boolean value
                [$group,] = explode(':', $name, 2);   // Split a string by a string
                if (!isset($grouped[$group])) {   // <p>Determine if a variable is set and is not <b>NULL</b>.</p>
                    $grouped[$group] = [];
                }
                $grouped[$group][] = $cmd;
            } else {
                $grouped['_default'][] = $cmd;
            }
        }

        // Sort groups
        ksort($grouped);   // Sort an array by key in ascending order

        return $grouped;
    }
    #endregion Command Handlers

    #region Output
    /**
     * Writes a plain line to stdout.
     */
    private function output(string $message): void {
        Cli::line($message);   // Outputs a line of text to the CLI.
    }

    #endregion Output

    /**
     * Registers all public methods on the given controller class that are
     * annotated with the `Command` attribute.
     *
     * @param OptionsInterface|class-string|class-string[] $controllerClasses   // Interface: Options
     *
     * @throws ReflectionException   // The ReflectionException class.
     */
    public function registerCommands(string|OptionsInterface $controllerClasses): void {   // Interface: Options
        if (is_string($controllerClasses))   // Find whether the type of a variable is string
            $controllerClasses = [$controllerClasses];   // Registers all public methods on the given controller class that are

        foreach($controllerClasses as $controllerClass)   // Registers all public methods on the given controller class that are
            $this->register($controllerClass);   // Registers all public methods on the given controller class that are
    }

    /**
     * Registers all public methods on the given controller class that are
     * annotated with the `Command` attribute.
     *
     * @param class-string $controllerClass
     *
     * @throws ReflectionException   // The ReflectionException class.
     */
    public function register(string $controllerClass): void {
        $reflection = new ReflectionClass($controllerClass);   // Constructs a ReflectionClass | Registers all public methods on the given controller class that are

        foreach($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {   // Gets an array of methods for the class.
            $commandAttrs = $method->getAttributes(Command::class);                  // @template T

            foreach($commandAttrs as $attr) {
                /** @var Command $command */   // Represents a command that can be executed, with a name, description,
                $command = $attr->newInstance();   // @var Command $command | Creates a new instance of the attribute with passed arguments
                $this->addCommand($command, $method, $controllerClass);   // Executes a registered command with the given arguments.
            }
        }
    }

    /**
     * Executes the router using the provided argv and returns the exit code.
     *
     * @throws Exception   // Exception is the base class for
     */
    public function run(): int {
        if (count($this->argv) < 2) {   // Counts all elements in an array, or something in an object.
            $this->showHelp();   // Displays help information for available commands or a specific command if provided.

            return 0;
        }

        $commandName = $this->argv[1];   // Raw argv passed to the router.

        if ($commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
            $this->showHelp();   // Displays help information for available commands or a specific command if provided.

            return 0;
        }

        if ($commandName === '--version') {
            $this->output($this->executable . ' v1.0.0');   // Writes a plain line to stdout.

            return 0;
        }

        if (!isset($this->commands[$commandName])) {   // <p>Determine if a variable is set and is not <b>NULL</b>.</p>
            static::$error->error("Command '$commandName' not found."); // Writes an error line (red) to stdout.   // Outputs an error message to the standard error stream.
            $this->output(PHP_EOL . "Run '$this->executable' to see all available commands." . PHP_EOL);     // Writes a plain line to stdout.

            return 1;
        }

        // Check for command-specific help
        $cmdArgs = array_slice($this->argv, 2);   // Extract a slice of the array
        if (in_array('--help', $cmdArgs) || in_array('-h', $cmdArgs)) {   // Checks if a value exists in an array
            $this->showHelp($commandName);   // Displays help information for available commands or a specific command if provided.

            return 0;
        }

        $cmd = $this->commands[$commandName];   // Registered commands map.

        try {
            $args = $this->parseArguments($cmdArgs, $cmd['params']);   // Parses argv into a final ordered argument list based on the parameter
            $controller = new $cmd['class']();
            $result = $controller->{$cmd['method']}(...$args);

            return is_int($result) ? $result : 0;   // Find whether the type of a variable is integer
        } catch (Exception $e) {                                                                         // Exception is the base class for
            static::$error->error($e->getMessage());                                                              // Writes an error line (red) to stdout.   // Outputs an error message to the standard error stream.
            $this->output(PHP_EOL . "Run '$this->executable $commandName --help' for usage information." . PHP_EOL);     // Writes a plain line to stdout.

            return 1;
        }
    }
}
