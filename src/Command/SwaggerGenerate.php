<?php

namespace OV\JsonRPCAPIBundle\Command;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ov:swagger:generate')]
class SwaggerGenerate extends Command
{
    public function __construct(
        private readonly string $ovJsonRpcApiSwaggerPath,
        private readonly array $swagger,
        private readonly MethodSpecCollection $methodSpecCollection,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //print_r($this->ovJsonRpcApiSwaggerPath.PHP_EOL);
        //print_r($this->swagger);

        print_r($this->methodSpecCollection->getMethodNames());

        return self::SUCCESS;
    }
}