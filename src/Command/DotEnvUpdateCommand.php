<?php

/*
 * This file is part of rimi-itk/undocker.
 *
 * (c) 2020 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

class DotEnvUpdateCommand extends Command
{
    protected static $defaultName = 'app:dot-env:update';

    /** @var LoggerInterface */
    private $logger;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->logger = new ConsoleLogger($output);

        $dir = getcwd();
        $dotEnvFilename = $dir.'/'.'.env.undocker.local';
        if (!file_exists($dotEnvFilename)) {
            throw new RuntimeException("File $dotEnvFilename does not exist");
        }
        $content = file_get_contents($dotEnvFilename);
        $env = new Dotenv();
        $variables = $env->parse($content);

        // Get docker-compose container names.
        $this->logger->debug('Getting container names');
        $process = new Process(['docker-compose', 'ps', '--services', '--filter=status=running']);
        $process->mustRun();
        $result = $process->getOutput();
        $containerNames = array_filter(explode(PHP_EOL, $result));

        if (empty($containerNames)) {
            $io->warning('No running containers found.');

            return 1;
        }

        // Map container names to ids.
        $containerIds = [];
        foreach ($containerNames as $name) {
            $this->logger->debug('Getting container id for {container}', ['container' => $name]);
            $process = new Process(['docker-compose', 'ps', '--quiet', $name]);
            $process->mustRun();
            $result = $process->getOutput();
            $containerIds[$name] = trim($result);
        }
        $containerNames = array_flip($containerIds);

        $containers = $this->getContainers($containerNames);

        if ($output->isDebug()) {
            $table = (new Table($output))
                ->setHeaderTitle('Containers')
                ->setHeaders(['Name', 'ID', 'Image', 'Ports']);
            foreach ($containers as $id => $details) {
                $table->addRow([$details['Name'], $id, $details['Image'], $details['Ports'],
                    json_encode($this->parsePorts($details['Ports']), JSON_PRETTY_PRINT),
                    ]);
            }
            $table->render();
        }

        $variables = [];
        foreach ($containers as $container) {
            $ports = $this->parsePorts($container['Ports']);

            foreach ($ports as $port) {
                if (isset($port['host']['port'])) {
                    $name = strtoupper($container['Name']).'_PORT';
                    $value = $port['host']['port'];
                    $variables[$name] = $value;
                }
            }
        }

        $updatedContent = $content;
        foreach ($variables as $name => $value) {
            $pattern = '/^'.preg_quote($name, '/').'=.*/';
            $replacement = $name.'='.$value;

            $this->logger->debug('Setting {name} = {value}', ['name' => $name, 'value' => $value]);

            $updatedContent = preg_replace($pattern, $replacement, $updatedContent);
        }

        if ($content === $updatedContent) {
            $io->success(sprintf('File %s is already up to date.', basename($dotEnvFilename)));
        } else {
            $helper = $this->getHelper('question');
            $output->writeln([
                str_repeat('-', 80),
                $content,
                str_repeat('-', 80),
            ]);
            $question = sprintf("Write\n\n%s\n\nto file %s? ", $updatedContent, $dotEnvFilename);
            if ($io->confirm($question)) {
                $this->logger->debug('Updating {file}', ['file' => $dotEnvFilename]);
                file_put_contents($dotEnvFilename, $updatedContent);
                $io->success(sprintf('File %s updated.', basename($dotEnvFilename)));
            }
        }

        return 0;
    }

    private function parsePorts(string $ports)
    {
        return array_map(
            static function ($spec) {
                $pattern = '@^(?P<host_domain>[\d.]+):(?P<host_port>\d+)->(?P<container_port>\d+)/(?P<container_protocol>.+)$@';
                if (preg_match($pattern, $spec, $matches)) {
                    return [
                      'host' => [
                          'domain' => $matches['host_domain'],
                          'port' => (int) $matches['host_port'],
                      ],
                      'container' => [
                          'port' => (int) $matches['container_port'],
                          'protocol' => (int) $matches['container_protocol'],
                      ],
                    ];
                }

                return null;
            },
            array_values(
                array_filter(
                    preg_split('/\s*,\s*/', $ports, null, PREG_SPLIT_NO_EMPTY),
                    static function ($spec) {
                        return false !== strpos($spec, '->');
                    }
                )
            )
        );
    }

    private function getContainers(array $names)
    {
        // Get all containers from docker
        $this->logger->debug('Getting container details');
        $process = new Process(['docker', 'ps', '--no-trunc', '--format', '\'{{json .}}\'']);
        $process->mustRun();
        $result = $process->getOutput();
        $containers = array_column(
            array_map(static function ($line) {
                $json = trim($line, "'");

                return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }, array_filter(explode(PHP_EOL, $result))),
            null,
            'ID'
        );

        $containers = array_filter($containers, static function ($id) use ($names) {
            return isset($names[$id]);
        }, ARRAY_FILTER_USE_KEY);

        // Add names
        foreach ($containers as $id => &$container) {
            $container['Name'] = $names[$id];
        }

        return $containers;
    }
}
