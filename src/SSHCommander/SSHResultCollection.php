<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use InvalidArgumentException;
use RuntimeException;

class SSHResultCollection implements SSHResultCollectionInterface
{
    const MATCHING_MODE_REGEX     = 'regex';
    const MATCHING_MODE_STRING_CS = 'string_cs';
    const MATCHING_MODE_STRING_CI = 'string_ci';

    protected $items = [];

    /**
     * This function is needed to implement ArrayAccess.
     *
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * This function is needed to implement ArrayAccess.
     *
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * This function is needed to implement ArrayAccess.
     *
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof SSHCommandResultInterface) {
            throw new RuntimeException(
                'SSHResultCollection only accepts instances ' .
                'of SSHCommandResultInterface'
            );
        }

        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * This function is needed to implement ArrayAccess.
     *
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    /**
     * Get all results as an array.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Helper function to see if all commands in the collection succeeded.
     *
     * @return bool
     */
    public function isOk(): bool
    {
        return !$this->hasFailedResults();
    }

    /**
     * Alias for self::hasFailedResults()
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->hasFailedResults();
    }

    /**
     * Get only those results from the collection that were successful.
     *
     * @return SSHResultCollectionInterface
     */
    public function successful(): SSHResultCollectionInterface
    {
        return $this->filter(function (SSHCommandResultInterface $result) {
            return $result->isOk();
        });
    }

    /**
     * Check if any of the results was successful.
     *
     * @return bool
     */
    public function hasSuccessfulResults(): bool
    {
        return (bool)$this->countSuccessfulResults();
    }

    /**
     * Count the total number of results that were successful.
     *
     * @return int
     */
    public function countSuccessfulResults(): int
    {
        return $this->count(function (SSHCommandResultInterface $result) {
            return $result->isOk();
        });
    }

    /**
     * Get only those results from the collection that failed.
     *
     * @return SSHResultCollectionInterface
     */
    public function failed(): SSHResultCollectionInterface
    {
        return $this->filter(function (SSHCommandResultInterface $result) {
            return $result->isError();
        });
    }

    /**
     * Check if any results from the collection are failed commands.
     *
     * @return bool
     */
    public function hasFailedResults(): bool
    {
        return (bool)$this->countFailedResults();
    }

    /**
     * Get the number of commands in the collection that have failed.
     *
     * @return int
     */
    public function countFailedResults(): int
    {
        return $this->count(function (SSHCommandResultInterface $result) {
            return $result->isError();
        });
    }

    /**
     * Check if any of the commands produced output matching the given pattern.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_REGEX
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return bool
     */
    public function hasResultsThatMatch(
        string $pattern,
        ?string $mode = self::MATCHING_MODE_REGEX
    ): bool {
        $first = $this->getFirstResultThatMatches($pattern, $mode);

        return ($first instanceof SSHCommandResultInterface)
            ? true
            : false;
    }

    /**
     * Check if any of the commands produced output containing the given pattern.
     *
     * See the docblock for function getContainFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return bool
     */
    public function hasResultsThatContain(
        string $pattern,
        ?string $mode = self::MATCHING_MODE_STRING_CI
    ): bool {
        $first = $this->getFirstResultThatContains($pattern, $mode);

        return ($first instanceof SSHCommandResultInterface)
            ? true
            : false;
    }

    /**
     * Get all results where produced output matches the given pattern or string.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_REGEX
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return SSHResultCollectionInterface
     */
    public function getResultsThatMatch(
        string $pattern,
        ?string $mode = self::MATCHING_MODE_REGEX
    ): SSHResultCollectionInterface {
        $match = $this->getMatchFunction($pattern, $mode);

        return $this->filter($match);
    }

    /**
     * Get all results where produced output contains the given substring.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return SSHResultCollectionInterface
     */
    public function getResultsThatContain(
        string $pattern,
        ?string $mode = self::MATCHING_MODE_STRING_CI
    ): SSHResultCollectionInterface {
        $match = $this->getContainFunction($pattern, $mode);

        return $this->filter($match);
    }

    /**
     * Get the first result where produced output matches the given pattern or string.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_REGEX
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return null|SSHCommandResultInterface
     */
    public function getFirstResultThatMatches(
        string $pattern,
        ?string $mode = self::MATCHING_MODE_REGEX
    ): ?SSHCommandResultInterface {
        $match = $this->getMatchFunction($pattern, $mode);

        return $this->first($match);
    }

    /**
     * Get the last result where produced output matches the given pattern or string.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_REGEX
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return null|SSHCommandResultInterface
     */
    public function getLastResultThatMatches(
        string $pattern,
        ?string $mode = null
    ): ?SSHCommandResultInterface {
        $match = $this->getMatchFunction($pattern, $mode);

        return $this->last($match);
    }

    /**
     * Get the first result where produced output contains the given substring.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return null|SSHCommandResultInterface
     */
    public function getFirstResultThatContains(
        string $pattern,
        ?string $mode = null
    ): ?SSHCommandResultInterface {
        $match = $this->getContainFunction($pattern, $mode);

        return $this->first($match);
    }

    /**
     * Get the last result where produced output contains the given substring.
     *
     * See the docblock for function getMatchFunction() for available matching
     * modes.
     *
     * @param string      $pattern
     * @param string|null $mode any of:
     *                          SSHResultCollection::MATCHING_MODE_STRING_CS
     *                          SSHResultCollection::MATCHING_MODE_STRING_CI
     *
     * @return null|SSHCommandResultInterface
     */
    public function getLastResultThatContains(
        string $pattern,
        ?string $mode = null
    ): ?SSHCommandResultInterface {
        $match = $this->getContainFunction($pattern, $mode);

        return $this->last($match);
    }

    /**
     * Get the first failed result from the collection.
     *
     * @return SSHCommandResultInterface|null
     */
    public function getFirstFailedResult(): ?SSHCommandResultInterface
    {
        return $this->first(function (SSHCommandResultInterface $result) {
            return $result->isError();
        });
    }

    /**
     * Get the last failed result from the collection.
     *
     * @return SSHCommandResultInterface|null
     */
    public function getLastFailedResult(): ?SSHCommandResultInterface
    {
        return $this->last(function (SSHCommandResultInterface $result) {
            return $result->isError();
        });
    }

    /**
     * Get the first successful result from the collection.
     *
     * @return SSHCommandResultInterface|null
     */
    public function getFirstSuccessfulResult(): ?SSHCommandResultInterface
    {
        return $this->first(function (SSHCommandResultInterface $result) {
            return $result->isOk();
        });
    }

    /**
     * Get the last successful result from the collection.
     *
     * @return SSHCommandResultInterface|null
     */
    public function getLastSuccessfulResult(): ?SSHCommandResultInterface
    {
        return $this->last(function (SSHCommandResultInterface $result) {
            return $result->isOk();
        });
    }

    /**
     * Return a collection resulted from running a callback function on each
     * element of the current collection.
     *
     * @param callable $function
     *
     * @return SSHResultCollectionInterface
     */
    public function map(callable $function): SSHResultCollectionInterface
    {
        $newCollection = new SSHResultCollection;

        foreach ($this->items as $key => $item) {
            $newCollection[$key] = $function($item, $key);
        }

        return $newCollection;
    }

    /**
     * Return a collection that only contains results for which the callback
     * function returns true.
     *
     * @param callable $function
     *
     * @return SSHResultCollectionInterface
     */
    public function filter(callable $function): SSHResultCollectionInterface
    {
        $newCollection = new SSHResultCollection;

        foreach ($this->items as $key => $item) {
            if ($function($item, $key)) {
                $newCollection[$key] = $item;
            }
        }

        return $newCollection;
    }

    /**
     * Return the first item from the collection where callback returns true. If
     * callback was not provided, just return the first item in the collection.
     *
     * @param callable $function
     *
     * @return SSHResultCollectionInterface
     */
    public function first(?callable $function = null): ?SSHCommandResultInterface
    {
        if (is_null($function)) {
            return !count($this->items)
                ? null
                : reset($this->items);
        }

        foreach ($this->items as $key => $item) {
            if ($function($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Return the last item from the collection where callback returns true. If
     * callback was not provided, just return the last item in the collection.
     *
     * @param callable $function
     *
     * @return SSHResultCollectionInterface
     */
    public function last(?callable $function = null): ?SSHCommandResultInterface
    {
        if (is_null($function)) {
            return !count($this->items)
                ? null
                : end($this->items);
        }

        $last = null;

        foreach ($this->items as $key => $item) {
            if ($function($item, $key)) {
                // quick and dirty
                $last = $item;
            }
        }

        return $last;
    }

    /**
     * Return the count of items in the collection that satisfy the given
     * callback. If the callback wasn't provided, just return the total count of
     * items in the collection.
     *
     * @param callable|null $function
     *
     * @return int
     */
    public function count(?callable $function = null): int
    {
        if (is_null($function)) {
            return count($this->items);
        }

        $count = 0;

        foreach ($this->items as $item) {
            if ($function($item)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Make this collection empty.
     */
    public function wipe(): void
    {
        $this->items = [];
    }

    /**
     * Build the function used to perform result matching to string, according to
     * selected matching mode.
     *
     * $mode can be one of the following:
     *
     * - SSHResultCollection::MATCHING_MODE_REGEX
     *   match against a full regular expression, example pattern:
     *   /package\s+(successfully|already)\s+installed/si
     *
     * - SSHResultCollection::MATCHING_MODE_STRING_CS
     *   case-sensitive full string match against a simple string pattern
     *
     * - SSHResultCollection::MATCHING_MODE_STRING_CI
     *   case-insensitive full string match against a simple string pattern
     *
     * @param string      $pattern
     * @param string|null $mode
     *
     * @return callable
     */
    protected function getMatchFunction(string $pattern, ?string $mode): callable
    {
        switch ($mode) {
            case self::MATCHING_MODE_REGEX:
                $match = function (SSHCommandResultInterface $result) use ($pattern) {
                    return preg_match($pattern, (string)$result);
                };
                break;
            case self::MATCHING_MODE_STRING_CS:
                $match = function (SSHCommandResultInterface $result) use ($pattern) {
                    return (string)$result === $pattern;
                };
                break;
            case self::MATCHING_MODE_STRING_CI:
                $match = function (SSHCommandResultInterface $result) use ($pattern) {
                    $slf = function_exists('mb_strtolower')
                        ? 'mb_strtolower'
                        : 'strtolower';

                    return $slf((string)$result) === $slf($pattern);
                };
                break;
            default:
                throw new InvalidArgumentException(
                    'Invalid matching mode was provided to SSHResultCollection'
                );
        }

        return $match;
    }

    /**
     * Get the function used to search for substring in the result string,
     * according to selected matching mode.
     *
     * $mode can be one of the following:
     *
     * - SSHResultCollection::MATCHING_MODE_STRING_CS
     *   case-sensitive full string match against a simple string pattern
     *
     * - SSHResultCollection::MATCHING_MODE_STRING_CI
     *   case-insensitive full string match against a simple string pattern
     *
     * @param string      $pattern
     * @param string|null $mode
     *
     * @return callable
     */
    protected function getContainFunction(string $pattern, ?string $mode): callable
    {
        switch ($mode) {
            case self::MATCHING_MODE_STRING_CS:
                $match = function (SSHCommandResultInterface $result) use ($pattern) {
                    $f = function_exists('mb_strpos') ? 'mb_strpos' : 'strpos';

                    return false !== $f((string)$result, $pattern);
                };
                break;
            case self::MATCHING_MODE_STRING_CI:
                $match = function (SSHCommandResultInterface $result) use ($pattern) {
                    $f = function_exists('mb_stripos') ? 'mb_stripos' : 'stripos';

                    return false !== $f((string)$result, $pattern);
                };
                break;
            default:
                throw new InvalidArgumentException(
                    'Invalid matching mode was provided to SSHResultCollection'
                );
        }

        return $match;
    }
}
