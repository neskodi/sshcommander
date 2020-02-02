<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Exceptions\InvalidCommandException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommand;
use stdClass;

class SSHCommandTest extends TestCase
{
    protected function createCommand(string $command): SSHCommandInterface
    {
        $defaultConfig = $this->getTestConfigAsArray();
        $command       = new SSHCommand($command, $defaultConfig);

        return $command;
    }

    public function testSetConfigArray(): void
    {
        $command = $this->createCommand('ls');

        $options = ['basedir' => '/test', 'timeout' => 12];
        $command->set($options);
        $options = $command->getConfig();

        $this->assertEquals($options->get('basedir'), '/test');
        $this->assertEquals($options->get('timeout'), 12);
    }

    public function testSetOption(): void
    {
        $command = $this->createCommand('ls');

        $command->set('basedir', '/test');
        $option = $command->getConfig('basedir');

        $this->assertEquals($option, '/test');
    }

    public function testPrependCommandString(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = 'cd /test';
        $command->prependCommand($commandToPrepend);
        $result = $command->getCommand();

        $this->assertEquals('cd /test;ls', $result);
    }

    public function testPrependCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');
        $command->set('delimiter_join_input', ';');

        $commandToPrepend = ['cd /test', 'pwd'];

        $command->prependCommand(implode(';', $commandToPrepend));
        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'cd /test',
            'pwd',
            'ls',
        ]), $result);
    }

    public function testPrependCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = ['cd /test', 'pwd'];
        $command->prependCommand($commandToPrepend);
        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'cd /test',
            'pwd',
            'ls',
        ]), $result);
    }

    public function testPrependCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = $this->createCommand('cd /test');

        $command->prependCommand($commandToPrepend);
        $result = $command->getCommand();

        $this->assertEquals('cd /test;ls', $result);
    }

    public function testPrependCommandInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        $command = $this->createCommand('ls');

        $command->prependCommand(null);
    }

    public function testSetCommandString(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = 'cd /test';
        $command->setCommand($commandToSet);
        $result = $command->getCommand();

        $this->assertEquals('cd /test', $result);
    }

    public function testSetCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = ['cd /test', 'pwd'];
        $command->setCommand(implode(';', $commandToSet));

        $result = $command->getCommand();

        $this->assertEquals('cd /test;pwd', $result);
    }

    public function testSetCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = ['cd /test', 'pwd'];
        $command->setCommand($commandToSet);
        $result = $command->getCommand();

        $this->assertEquals('cd /test;pwd', $result);
    }

    public function testSetCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = $this->createCommand('cd /test');
        $command->setCommand($commandToSet);

        $result = $command->getCommand();

        $this->assertEquals('cd /test', $result);
    }

    public function testSetCommandInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        $command = $this->createCommand('ls');

        $command->setCommand(null);
    }

    public function testAppendCommandString(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = 'cd /test';
        $command->appendCommand($commandToAppend);

        $result = $command->getCommand();

        $this->assertEquals('ls;cd /test', $result);
    }

    public function testAppendCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = ['cd /test', 'pwd'];
        $command->appendCommand(implode(';', $commandToAppend));

        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'ls',
            'cd /test',
            'pwd',
        ]), $result);
    }

    public function testAppendCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = ['cd /test', 'pwd'];

        $command->appendCommand($commandToAppend);
        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'ls',
            'cd /test',
            'pwd',
        ]), $result);
    }

    public function testAppendCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = $this->createCommand('cd /test');

        $command->appendCommand($commandToAppend);
        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'ls',
            'cd /test',
        ]), $result);
    }

    public function testAppendCommandInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        $command = $this->createCommand('ls');

        $command->appendCommand(null);
    }

    public function testWrapCommand(): void
    {
        $command = $this->createCommand('ls');
        $pattern = 'timeout 5 %s';
        $command->wrap($pattern);

        $result = $command->getCommand();

        $this->assertEquals('timeout 5 ls', $result);
    }

    public function testCreateCommandFromString(): void
    {
        $command = $this->createCommand(' ls ');

        $result = $command->getCommand();

        $this->assertEquals('ls', $result);
    }

    public function testCreateCommandFromStringMulti(): void
    {
        $multiCommandString = ' cd /test; ls ';

        $command = $this->createCommand($multiCommandString);

        $result = $command->getCommand();

        $this->assertEquals(trim($multiCommandString), $result);
    }

    public function testCreateCommandFromArray(): void
    {
        $command = new SSHCommand(
            [' cd /test ', ' ls '],
            $this->getTestConfigAsArray()
        );

        $result = $command->getCommand();

        $this->assertEquals(implode(';', [
            'cd /test',
            'ls',
        ]), $result);
    }

    public function testCreateCommandFromObject(): void
    {
        $commandSource = $this->createCommand(' ls ');
        $command       = new SSHCommand(
            $commandSource,
            $this->getTestConfigAsArray()
        );

        $result = $command->getCommand();

        $this->assertEquals('ls', $result);
    }

    public function testCreateCommandFromInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        $config = $this->getTestConfigAsArray();

        new SSHCommand(new stdClass, $config);
    }

    public function testToLoggableString(): void
    {
        $command = new SSHCommand(
            ["echo \"continued \nstring\"", 'ls'],
            $this->getTestConfigAsArray()
        );

        $result = $command->toLoggableString();
        $this->assertEquals('echo "continued \nstring";ls', $result);
    }

    public function test__toString(): void
    {
        $config = $this->getTestConfigAsArray();
        $config['delimiter_join_input'] = '~~';

        $command = new SSHCommand(
            [' cd /test ', ' ls '],
            $config
        );

        $result = (string)$command;
        $this->assertEquals('cd /test~~ls', $result);
    }
}

