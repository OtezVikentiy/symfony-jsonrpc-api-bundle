<?php

namespace OV\JsonRPCAPIBundle\Command;

use OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ov:swagger:generate')]
final class SwaggerGenerate extends Command
{
    public function __construct(
        private readonly string $ovJsonRpcApiSwaggerPath,
        private readonly array $swagger,
        private readonly SwaggerSchemaBuilder $schemaBuilder,
        private readonly bool $passToOutput = false,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ReflectionException
     * @noinspection PhpUnused
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resolvedDir = null;
        if (!$this->passToOutput) {
            $resolvedDir = $this->resolveDirectory();
            if ($resolvedDir === null) {
                $output->writeln(sprintf(
                    '<error>Swagger output directory "%s" does not exist or is not writable.</error>',
                    $this->ovJsonRpcApiSwaggerPath,
                ));

                return self::FAILURE;
            }
        }

        foreach ($this->swagger as $name => $item) {
            $yaml = $this->schemaBuilder->build($item);

            if ($this->passToOutput) {
                $output->writeln($yaml);
                continue;
            }

            $target = $resolvedDir . DIRECTORY_SEPARATOR . $name . '.yaml';
            file_put_contents($target, $yaml);
        }

        return self::SUCCESS;
    }

    private function resolveDirectory(): ?string
    {
        $path = rtrim($this->ovJsonRpcApiSwaggerPath, DIRECTORY_SEPARATOR);
        if ($path === '') {
            return null;
        }
        $real = realpath($path);
        if ($real === false || !is_dir($real) || !is_writable($real)) {
            return null;
        }

        return $real;
    }
}
