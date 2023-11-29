<?php

namespace OV\JsonRPCAPIBundle\Tests\Command;

use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use OV\JsonRPCAPIBundle\Command\SwaggerGenerate;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\GetData\GetDataRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetDataMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHelloMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySum\NotifySumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySumMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2Method;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SwaggerGenerateTest extends TestCase
{
    private $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new SwaggerGenerate(
            '/app/public',
            [
                'api_v1' => [
                    'api_version' => '1',
                    'base_path' => 'http://localhost:35080',
                    'auth_token_name' => 'X-AUTH-TOKEN',
                    'auth_token_test_value' => '2f1f6aee7d994528fde6e47a493cc097',
                    'info' => [
                        'title' => 'title',
                        'description' => 'description',
                        'terms_of_service_url' => 'terms_of_service_url',
                        'contact' => [
                            'name' => 'name',
                            'url' => 'url',
                            'email' => 'email',
                        ],
                        'license' => 'license',
                        'licenseUrl' => 'licenseUrl',
                    ]
                ]
            ],
            $this->prepareMethodSpecCollection(),
            true
        ));
        $command = $application->find('ov:swagger:generate');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->commandTester = null;
    }

    public function testExecute()
    {
        $this->commandTester->execute([]);
        $this->assertEquals(file_get_contents('./swagger_generate_test_fixture.yaml'), $this->commandTester->getDisplay());
    }

    private function prepareMethodSpecCollection(): MethodSpecCollection
    {
        $annotationReader = new AnnotationReader();

        $methodSpecCollection = new MethodSpecCollection();
        foreach ($this->getMethodSpecs() as $methodSpec) {
            $class = $methodSpec->getMethodClass();
            $methodReflectionClass = new ReflectionClass(new $class());
            $classAnnotation = $annotationReader->getClassAnnotation($methodReflectionClass, JsonRPCAPI::class);
            $methodName = null;
            if (!is_null($classAnnotation)) {
                $methodName = $classAnnotation->getMethodName();
            } else {
                $attributes = $methodReflectionClass->getAttributes(JsonRPCAPI::class);
                foreach ($attributes as $attribute) {
                    if ($attribute->getName() === JsonRPCAPI::class) $methodName = $attribute->getArguments()['methodName'];
                }
            }

            if (is_null($methodName)) throw new Exception('Could not define method name');
            $methodSpecCollection->addMethodSpec($methodName, $methodSpec);
        }

        return $methodSpecCollection;
    }

    private function getMethodSpecs(): array
    {
        return [
            new MethodSpec(
                GetDataMethod::class,
                'POST',
                '',
                '',
                false,
                'get_data',
                [],
                [],
                GetDataRequest::class,
                [],
                []
            ),
            new MethodSpec(
                NotifyHelloMethod::class,
                'POST',
                '',
                '',
                false,
                'notify_hello',
                [['name' => 'params', 'type' => 'array']],
                [],
                NotifyHelloRequest::class,
                ['params' => 'setParams'],
                ['params' => 'array']
            ),
            new MethodSpec(
                NotifySumMethod::class,
                'POST',
                '',
                '',
                false,
                'notify_sum',
                [['name' => 'params', 'type' => 'array']],
                [],
                NotifySumRequest::class,
                ['params' => 'setParams'],
                ['params' => 'array']
            ),
            new MethodSpec(
                Subtract2Method::class,
                'POST',
                '',
                '',
                false,
                'subtract2',
                [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
                [],
                Subtract2Request::class,
                ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
                ['subtrahend' => 'int', 'minuend' => 'int']
            ),
            new MethodSpec(
                SubtractMethod::class,
                'POST',
                '',
                '',
                false,
                'subtract',
                [['name' => 'params', 'type' => 'array']],
                [],
                SubtractRequest::class,
                ['params' => 'setParams'],
                ['params' => 'array']
            ),
            new MethodSpec(
                SumMethod::class,
                'POST',
                '',
                '',
                false,
                'sum',
                [['name' => 'params', 'type' => 'array']],
                [],
                SumRequest::class,
                ['params' => 'setParams'],
                ['params' => 'array']
            ),
            new MethodSpec(
                TestMethod::class,
                'POST',
                '',
                '',
                false,
                'test',
                [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
                [['name' => 'id', 'type' => 'int']],
                TestRequest::class,
                ['id' => 'setId', 'title' => 'setTitle'],
                ['id' => 'int', 'title' => 'string']
            ),
            new MethodSpec(
                UpdateMethod::class,
                'PUT',
                '',
                '',
                false,
                'update',
                [['name' => 'params', 'type' => 'array']],
                [],
                UpdateRequest::class,
                ['params' => 'setParams'],
                ['params' => 'array']
            ),
        ];
    }
}
