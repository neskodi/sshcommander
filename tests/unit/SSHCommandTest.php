<?php

namespace Neskodi\SSHCommander\Tests\unit;

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

    public function testSetOptions(): void
    {
        $command = $this->createCommand('ls');

        $options = ['basedir' => '/test', 'timeout_command' => 10];
        $command->setOptions($options);
        $options = $command->getOptions();

        $this->assertEquals($options['basedir'], '/test');
        $this->assertEquals($options['timeout_command'], 10);
    }

    public function testSetOption(): void
    {
        $command = $this->createCommand('ls');

        $command->setOption('basedir', '/test');
        $option = $command->getOption('basedir');

        $this->assertEquals($option, '/test');
    }

    public function testPrependCommandString(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = 'cd /test';
        $command->prependCommand($commandToPrepend);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'ls',
        ]);
    }

    public function testPrependCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');
        $command->setOption('delimiter_split_input', ';');

        $commandToPrepend = ['cd /test', 'pwd'];

        $command->prependCommand(implode(';', $commandToPrepend));
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'pwd',
            'ls',
        ]);
    }

    public function testPrependCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = ['cd /test', 'pwd'];
        $command->prependCommand($commandToPrepend);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'pwd',
            'ls',
        ]);
    }

    public function testPrependCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToPrepend = $this->createCommand('cd /test');

        $command->prependCommand($commandToPrepend);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'ls',
        ]);
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
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
        ]);
    }

    public function testSetCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');
        $command->setOption('delimiter_split_input', ';');

        $commandToSet = ['cd /test', 'pwd'];

        $command->setCommand(implode(';', $commandToSet));
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'pwd',
        ]);
    }

    public function testSetCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = ['cd /test', 'pwd'];
        $command->setCommand($commandToSet);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'pwd'
        ]);
    }

    public function testSetCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToSet = $this->createCommand('cd /test');

        $command->setCommand($commandToSet);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
        ]);
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
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
            'cd /test',
        ]);
    }

    public function testAppendCommandStringMulti(): void
    {
        $command = $this->createCommand('ls');
        $command->setOption('delimiter_split_input', ';');

        $commandToAppend = ['cd /test', 'pwd'];

        $command->appendCommand(implode(';', $commandToAppend));
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
            'cd /test',
            'pwd',
        ]);
    }

    public function testAppendCommandArray(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = ['cd /test', 'pwd'];

        $command->appendCommand($commandToAppend);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
            'cd /test',
            'pwd',
        ]);
    }

    public function testAppendCommandObject(): void
    {
        $command = $this->createCommand('ls');

        $commandToAppend = $this->createCommand('cd /test');

        $command->appendCommand($commandToAppend);
        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
            'cd /test',
        ]);
    }

    public function testAppendCommandInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        $command = $this->createCommand('ls');

        $command->appendCommand(null);
    }

    public function testCreateCommandFromString(): void
    {
        $command = $this->createCommand(' ls ');

        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
        ]);
    }

    public function testCreateCommandFromStringMulti(): void
    {
        $command = $this->createCommand('cd /test; ls');
        $command->setOption('delimiter_split_input', ';');

        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'ls',
        ]);
    }

    public function testCreateCommandFromArray(): void
    {
        $command = new SSHCommand(
            [' cd /test ', ' ls '],
            $this->getTestConfigAsArray()
        );

        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'cd /test',
            'ls',
        ]);
    }

    public function testCreateCommandFromObject(): void
    {
        $commandSource = $this->createCommand(' ls ');
        $command       = new SSHCommand(
            $commandSource,
            $this->getTestConfigAsArray()
        );

        $result = $command->getCommands(false, false);

        $this->assertEquals($result, [
            'ls',
        ]);
    }

    public function testCreateCommandFromInvalid(): void
    {
        $this->expectException(InvalidCommandException::class);

        new SSHCommand(new stdClass);
    }

    public function testToLoggableString(): void
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error' => true,
            'basedir'        => '/start',
        ]);

        $result = $command->toLoggableString();
        $this->assertEquals('set -e;cd /start;cd /test;ls', $result);
        $result = $command->toLoggableString('~~');
        $this->assertEquals('set -e~~cd /start~~cd /test~~ls', $result);
    }

    public function test__toString(): void
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error'       => true,
            'basedir'              => '/start',
            'delimiter_join_input' => ';',
        ]);

        $result = (string)$command;
        $this->assertEquals('set -e;cd /start;cd /test;ls', $result);
    }

    public function testGetCommandsAsStringPrepared()
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error'       => true,
            'basedir'              => '/start',
            'delimiter_join_input' => ';',
        ]);

        $result = $command->getCommands(true, true);
        $this->assertEquals('set -e;cd /start;cd /test;ls', $result);
    }

    public function testGetCommandsAsStringRaw()
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error'       => true,
            'basedir'              => '/start',
            'delimiter_join_input' => ';',
        ]);

        $result = $command->getCommands(true, false);
        $this->assertEquals('cd /test;ls', $result);
    }

    public function testGetCommandsAsArrayPrepared()
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error'       => true,
            'basedir'              => '/start',
            'delimiter_join_input' => ';',
        ]);

        $result = $command->getCommands(false, true);
        $this->assertEquals($result, [
            'set -e',
            'cd /start',
            'cd /test',
            'ls',
        ]);
    }

    public function testGetCommandsAsArrayRaw()
    {
        $command = new SSHCommand(
            ['cd /test', 'ls'],
            $this->getTestConfigAsArray()
        );

        $command->setOptions([
            'break_on_error'       => true,
            'basedir'              => '/start',
            'delimiter_join_input' => ';',
        ]);

        $result = $command->getCommands(false, false);
        $this->assertEquals($result, [
            'cd /test',
            'ls',
        ]);
    }
}

