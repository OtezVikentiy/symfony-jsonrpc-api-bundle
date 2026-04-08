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
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ReflectionException
     * @noinspection PhpUnused
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->swagger as $name => $item) {
            $yaml = $this->schemaBuilder->build($item);

            if ($this->passToOutput) {
                $output->writeln($yaml);
            } else {
                file_put_contents(sprintf('%s%s.yaml', $this->ovJsonRpcApiSwaggerPath, $name), $yaml);
            }
        }

        return self::SUCCESS;
    }
}
