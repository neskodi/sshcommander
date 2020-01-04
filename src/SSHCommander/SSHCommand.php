<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUndefinedVariableInspection */

/** @noinspection PhpUnusedLocalVariableInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\InvalidCommandException;
use Neskodi\SSHCommander\Exceptions\EmptyCommandException;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;

class SSHCommand implements SSHCommandInterface, ConfigAwareInterface
{
    use ConfigAware {
        setOption as protected configAwareSetOption;
        mergeConfig as protected configAwareMergeConfig;
    }

    /**
     * @var string
     */
    protected $command = '';

    /**
     * Command constructor.
     *
     * @param string|array             $command - single command as string
     *                                          - multiple commands as strings separated by newlone
     *                                          - multiple commands as array
     * @param array|SSHConfigInterface $config
     */
    public function __construct($command, $config = [])
    {
        $initialConfig = ($command instanceof SSHCommandInterface)
            ? $command->getConfig()
            : [];

        $this->setConfig($initialConfig);

        if (!empty($config)) {
            $this->mergeConfig($config);
        }

        $this->setCommand($command);
    }

    /**
     * Fluent setter for the command to execute.
     *
     * @param array|string|SSHCommandInterface $command
     *   - single-line command as string
     *   - multi-line command as string
     *   - multiple commands as array
     *
     * @return SSHCommandInterface
     */
    public function setCommand($command): SSHCommandInterface
    {
        $command = $this->convertUserInputToCommandString($command);
        $this->validateCommand($command);
        $this->command = $command;

        return $this;
    }

    /**
     * Get the command as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getCommand();
    }

    /**
     * Return the commands as a string in a format suitable for logging.
     *
     * @return string
     */
    public function toLoggableString(): string
    {
        return str_replace(["\r", "\n"], ['\r', '\n'], $this->getCommand());
    }

    /**
     * Get command as a string.
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * This override is here so that SSHCommandInterface can declare the return
     * type (trait is too generic and can't assume any return types).
     *
     * @param string $key
     * @param        $value
     *
     * @return SSHCommandInterface
     */
    public function setOption(string $key, $value): SSHCommandInterface
    {
        return $this->configAwareSetOption($key, $value);
    }

    /**
     * This override is here so that SSHCommandInterface can declare the return
     * type (trait is too generic and can't assume any return types).
     *
     * @param      $config
     * @param bool $missingOnly
     *
     * @return SSHCommandInterface
     */
    public function mergeConfig($config, bool $missingOnly = false): SSHCommandInterface
    {
        return $this->configAwareMergeConfig($config, $missingOnly);
    }

    /**
     * Appends a command or commands to the end of the execution sequence.
     *
     * @param string|array $command
     *
     * @return $this
     */
    public function appendCommand($command): SSHCommandInterface
    {
        $command = $this->convertUserInputToCommandString($command);
        $this->validateCommand($command);

        $this->command = implode(
            $this->getConfig('delimiter_join_input'),
            [
                $this->command,
                $command,
            ]
        );

        return $this;
    }

    /**
     * Prepends a command or commands to the beginning of the execution sequence.
     *
     * @param array|string $command
     *
     * @return $this
     */
    public function prependCommand($command): SSHCommandInterface
    {
        $command = $this->convertUserInputToCommandString($command);
        $this->validateCommand($command);

        $this->command = implode(
            $this->getConfig('delimiter_join_input'),
            [
                $command,
                $this->command,
            ]
        );

        return $this;
    }

    /**
     * If this command is a multi-line script, get it as a single line.
     */
    public function singleLine()
    {
        // TODO: implement real singleLine through tokenization
        $command = $this->getCommand();

        $command = preg_replace('/[\r\n]+/', ';', $command);

        return $command;
    }


    /**
     * Cast user's input from any permitted type to string, or throw an
     * exception if this is not possible.
     *
     * @param mixed $command
     *
     * @return string
     */
    protected function convertUserInputToCommandString($command): string
    {
        switch (gettype($command)) {
            case 'string':
                $result = trim($command);
                break;
            case 'array':
                $result = implode(
                    $this->getConfig('delimiter_join_input'),
                    array_map('trim', $command)
                );
                break;
            case 'object':
                if (!$command instanceof SSHCommandInterface) {
                    throw new InvalidCommandException(get_class($command));
                }
                $result = $command->getCommand();
                break;
            default:
                throw new InvalidCommandException(gettype($command));
        }

        return trim($result);
    }

    /**
     * Verify that the user has provided a non-empty string that can be supplied
     * to shell as a command.
     *
     * @param string $command
     */
    protected function validateCommand(string $command): void
    {
        if (empty($command)) {
            throw new EmptyCommandException;
        }
    }

    /**
     * Config passed to command is not required to contain valid
     * or any at all connection information, so ask SSHConfig to skip
     * validation.
     *
     * @return bool
     */
    protected function skipConfigValidation(): bool
    {
        return true;
    }
}
