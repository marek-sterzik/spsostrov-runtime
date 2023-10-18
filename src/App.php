<?php

namespace SPSOstrov\Runtime;
use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\Operand;
use Exception;

class App
{
    private $config;

    public function __construct($composerAutoloadPath)
    {
        $rootDir = dirname(Path::canonize(dirname($composerAutoloadPath)));
        $this->config = (new RuntimeConfig($rootDir))->get();
        $this->config['rootDir'] = $rootDir;
        $this->config['argv0'] = null;
    }

    public function run($argv)
    {
        $this->config['argv0'] = $argv[0];
        array_shift($argv);

        $getopt = $this->createGlobalGetOpt();


        try {
            $getopt->process($argv);
        } catch (Exception $e) {
            fprintf(STDERR, "Error: Cannot parse options.\n");
            fprintf(STDERR, "For help use: %s --help\n", $this->config['argv0']);
            return 1;
        }
        $options = $getopt->getOptions();
        $command = $getopt->getOperand('command');
        $args = $getopt->getOperand('args');

        if ($options['help'] ?? false) {
            if ($command === null) {
                $this->printGlobalHelp();
            } else {
                $commandObj = Command::get($command, $this->config);
                $this->printCommandHelp($commandObj);
            }
            return 1;
        }

        if ($options['version'] ?? false) {
            $this->printVersionInfo();
            return 1;
        }

        if ($command === null) {
            $this->printGlobalHelp();
            return 1;
        }

        $commandObj = Command::get($command, $this->config);

        if ($commandObj === null) {
            fprintf(STDERR, "Unknown command: %s\n", $command);
            return 1;
        }

        $options = $this->getCommandOptions($commandObj, $args);

        if ($options['options']['help'] ?? false) {
            $this->printCommandHelp($commandObj);
            return 1;
        }

        if ($options['options']['version'] ?? false) {
            $this->printVersionInfo();
            return 1;
        }

        if ($commandObj->getPassArgsAsJson()) {
            if ($options === null) {
                fprintf(STDERR, "Cannot parse options.\n");
                return 1;
            }
            $args = [json_encode($options)];
        }

        return $commandObj->invoke($this->config, $args);
    }

    private function getCommandOptions($command, $args)
    {
        $getopt = $this->createCommandGetOpt($command, true);
        try {
            $getopt->process($args);
        } catch (Exception $e) {
            return null;
        }
        $data = [];
        $data['options'] = $getopt->getOptions();
        $data['operands'] = $getopt->getOperands();
        $namedOperands = [];
        foreach ($command->getOperands() as $op) {
            $name = $op[0];
            $namedOperands[$name] = $getopt->getOperand($name);
        }
        $data['namedOperands'] = $namedOperands;
        return $data;
    }

    private function createCommandsDescriptor()
    {
        $descriptor = [];
        $maxLen = 0;
        foreach (Command::getAll($this->config) as $name => $command) {
            $maxLen = max($maxLen, strlen($name));
            $descriptor[] = ["command" => $name, "spaces" => "", "description" => $command->getDescription()];
        }
        foreach ($descriptor as &$desc) {
            $n = $maxLen - strlen($desc['command']);
            $desc['spaces'] = str_repeat(" ", $n);
        }
        return $descriptor;
    }

    private function printGlobalHelp()
    {
        $getopt = $this->createGlobalGetOpt();
        fprintf(STDERR, "Usage:\n  " . $this->config['argv0'] . " <command> [options] [args]\n\n");
        fprintf(STDERR, $getopt->getHelpText());
        fprintf(STDERR, "Available commands:\n");
        foreach ($this->createCommandsDescriptor() as $command) {
            if ($command['description'] !== null) {
                fprintf(STDERR, "  %s%s  %s\n", $command['command'], $command['spaces'], $command['description']);
            } else {
                fprintf(STDERR, "  %s\n", $command['command']);
            }
        }
        
    }

    private function printCommandHelp($command)
    {
        $description = $command->getDescription();
        fprintf(STDERR, "Usage:\n  " . $this->config['argv0'] . " " . $command->getName() . " [options] [args]\n");
        if ($description !== null) {
            fprintf(STDERR, "\n$description\n\n");
        }
        $getopt = $this->createCommandGetOpt($command, false);
        fprintf(STDERR, $getopt->getHelpText());
        $help = $command->getHelp();
        if ($help !== null) {
            fprintf(STDERR, "$help\n");
        }
    }

    private function printVersionInfo()
    {
        fprintf(STDERR, "Error: version info not yet available!\n");
    }

    private function getControlOpts()
    {
        return [
            ["h", "help", "no", "Show help"],
            ["v", "version", "no", "Show version"]
        ];
    }

    private function getControlOperands()
    {
        return [
            ['command', 'optional'],
            ['args', 'multiple']
        ];
    }

    private function createOption($descriptor)
    {
        switch ($descriptor[2]) {
            case 'no':
                $type = GetOpt::NO_ARGUMENT;
                break;
            case 'required':
                $type = GetOpt::REQUIRED_ARGUMENT;
                break;
            case 'optional':
                $type = GetOpt::OPTIONAL_ARGUMENT;
                break;
            case 'multiple':
                $type = GetOpt::MULTIPLE_ARGUMENT;
                break;
            default:
                return null;
        }
        $option = Option::create($descriptor[0], $descriptor[1], $type);
        $description = $descriptor[3] ?? null;
        if ($description !== null) {
            $option = $option->setDescription($description);
        }
        return $option;
    }

    private function createOperand($descriptor)
    {
        switch ($descriptor[1]) {
            case 'required':
                $type = Operand::REQUIRED;
                break;
            case 'optional':
                $type = Operand::OPTIONAL;
                break;
            case 'multiple':
                $type = Operand::MULTIPLE;
                break;
            default:
                return null;
        }
        $operand = Operand::create($descriptor[0], $type);
        return $operand;
    }

    private function createGlobalGetOpt()
    {
        return $this->createGetOpt($this->getControlOpts(), $this->getControlOperands());
    }

    private function createCommandGetOpt($command, $mergeControl)
    {
        $options = $command->getOptions();
        if ($mergeControl) {
            $options = array_merge($this->getControlOpts(), $options);
        }
        return $this->createGetOpt($options, $command->getOperands());
    }

    private function createGetOpt($opts, $operands = [])
    {
        $realOpts = [];
        foreach ($opts as $opt) {
            $opt = $this->createOption($opt);
            if ($opt !== null) {
                $realOpts[] = $opt;
            }
        }

        $realOperands = [];
        foreach ($operands as $operand) {
            $operand = $this->createOperand($operand);
            if ($operand !== null) {
                $realOperands[] = $operand;
            }
        }
        $getopt = new GetOpt($realOpts);
        $getopt->addOperands($realOperands);
        $getopt->getHelp()->setUsageTemplate(__DIR__ . "/../usage-template.php");
        return $getopt;
    }
}
