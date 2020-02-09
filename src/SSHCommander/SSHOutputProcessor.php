<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\OutputProcessorInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;

class SSHOutputProcessor implements OutputProcessorInterface
{
    use ConfigAware;

    /**
     * @var string
     */
    protected $stdOut = '';

    /**
     * @var string
     */
    protected $stdErr = '';

    /**
     * SSHOutputProcessor constructor.
     *
     * @param SSHConfigInterface $config
     */
    public function __construct(SSHConfigInterface $config)
    {
        $this->setConfig($config);
    }

    /**
     * Add the new line(s) to the output (stdout).
     *
     * @param string $output
     */
    public function add(string $output): void
    {
        $this->stdOut .= $output;
    }

    /**
     * If stderr is a separate stream, add the new line(s) to stderr.
     *
     * @param string $output
     */
    public function addErr(string $output): void
    {
        $this->stdErr .= $output;
    }

    /**
     * Return the (optionally) cleaned output split into multiple lines.
     *
     * @param bool $clean
     *
     * @return array
     */
    public function get(bool $clean = true): array
    {
        return $clean ?
            $this->cleanAndSplit($this->stdOut) :
            $this->split($this->stdOut);
    }

    /**
     * If stderr is a separate stream, return it optionally cleaned and split
     * into separate lines.
     *
     * @param bool $clean
     *
     * @return array
     */
    public function getErr(bool $clean = true): array
    {
        return $clean ?
            $this->cleanAndSplit($this->stdErr) :
            $this->split($this->stdErr);
    }

    /**
     * Get the raw stdout as it was collected from the stream.
     *
     * @return array
     */
    public function getRaw(): string
    {
        return $this->stdOut;
    }

    /**
     * Get the raw stderr as it was collected from the stream.
     *
     * @return array
     */
    public function getRawErr(): string
    {
        return $this->stdErr;
    }

    /**
     * See if current received output from the command contains a command
     * prompt, as defined by the config value 'prompt_regex'.
     *
     * Also provides the ability to check any arbitrary string for prompt, by
     * passing it as an optional argument.
     *
     * @param null|string $text the string to test
     *
     * @return bool
     */
    public function hasPrompt(?string $text = null): bool
    {
        $testedString = $text ?? $this->stdOut;

        $regex = $this->getConfig()->getPromptRegex();

        return $this->hasExpectedOutputRegex($testedString, $regex);
    }

    /**
     * See if current received output from the command contains the specified
     * marker, which is matched via a regular expression, like prompt.
     *
     * @param string $markerRegex
     *
     * @return bool
     */
    public function hasMarker(string $markerRegex): bool
    {
        return $this->hasExpectedOutputRegex($this->stdOut, $markerRegex);
    }

    /**
     * Erase the prompts and original commands from the output, and return it
     * split into separate lines.
     *
     * @param string $text
     *
     * @return array
     */
    protected function cleanAndSplit(string $text = ''): array
    {
        $clean = $this->clean($text);

        return $this->split($clean);
    }

    /**
     * SSH2::read() returns the entire interactive buffer, including the command
     * itself and the command prompt in the end. We are only interested in the
     * command output, so we will strip off these artifacts.
     *
     * @param string $text
     *
     * @return string
     */
    protected function clean(string $text = ''): string
    {
        $firstCommandRegex = '/^.*?(\r\n|\r|\n)/';
        $promptRegex       = $this->getConfig()->getPromptRegex();

        // carefully inject command after prompt into prompt regex
        $promptRegexWithCommand = preg_replace(
            '/^(.)(.+?)(\\$)?\\1([a-z]*)$/',
            '\1\2.*(\r\n|\r|\n)\1\4',
            $promptRegex
        );

        // clean out the first subcommand from the beginning
        $text = preg_replace($firstCommandRegex, '', $text);

        // clean out all subsequent prompts with following commands
        $text = preg_replace($promptRegexWithCommand, '', $text);

        // clean out the command prompt from the end
        $text = preg_replace($promptRegex, '', $text);

        return $text;
    }

    /**
     * Split the output into separate lines by any sequence of carriage return /
     * line feed characters.
     *
     * @param string $text
     *
     * @return array
     */
    protected function split(string $text = ''): array
    {
        // see if user wants to split by regular expression
        if ($delim = $this->getConfig('delimiter_split_output_regex')) {
            return preg_split($delim, $text) ?: [];
        }

        // see if user wants to explode by a simple delimiter
        if ($delim = $this->getConfig('delimiter_split_output')) {
            return explode($delim, $text) ?: [];
        }

        // otherwise no splitting can be performed
        return [$text];
    }

    /**
     * Check if current received output from the command already contains a
     * substring expected by user.
     *
     * @param string $output
     * @param string $expect
     *
     * @return bool
     *
     * @noinspection PhpUnused
     */
    protected function hasExpectedOutputSimple(string $output, string $expect)
    {
        $strPosFunction = function_exists('mb_strpos') ? 'mb_strpos' : 'strpos';

        return false !== $strPosFunction($output, $expect);
    }

    /**
     * Check if current received output matches the regular expression expected
     * by user.
     *
     * @param string $output
     * @param string $expect
     *
     * @return false|int
     */
    protected function hasExpectedOutputRegex(string $output, string $expect)
    {
        return preg_match($expect, $output);
    }
}
