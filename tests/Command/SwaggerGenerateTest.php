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
use OV\JsonRPCAPIBundle\RPC\V1\GetPicture\Request as GetPictureRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetPictureMethod;
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
                    'base_path' => 'http://localhost.{azaza}:{port}',
                    'base_path_description' => 'Production server (uses live data)',
                    'test_path' => 'http://localhost.{domain}:35080',
                    'test_path_description' => 'Sandbox server (uses test data)',
                    'base_path_variables' => [
                        [
                            'name' => 'azaza',
                            'value' => 'ololo',
                        ],
                        [
                            'name' => 'port',
                            'value' => '35080',
                        ],
                    ],
                    'test_path_variables' => [
                        [
                            'name' => 'domain',
                            'value' => 'testoviy',
                        ],
                    ],
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
        file_put_contents(__DIR__.'/swagger_generate_test_fixture.yaml', $this->commandTester->getDisplay());
        $this->assertEquals(file_get_contents(__DIR__.'/swagger_generate_test_fixture.yaml'), $this->commandTester->getDisplay());
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
            $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);
        }

        return $methodSpecCollection;
    }

    private function getMethodSpecs(): array
    {

        return [
            new MethodSpec(
                methodClass: GetDataMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'get_data',
                allParameters: [],
                requiredParameters: [],
                request: GetDataRequest::class,
                requestSetters: [],
                validators: [],
                roles: [],
                tags: [],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: NotifyHelloMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'notify_hello',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: NotifyHelloRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => 'array'],
                roles: [],
                tags: [],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: NotifySumMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'notify_sum',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: NotifySumRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => 'array'],
                roles: [],
                tags: ['math'],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: Subtract2Method::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'subtract2',
                allParameters: [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
                requiredParameters: [],
                request: Subtract2Request::class,
                requestSetters: ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
                validators: ['subtrahend' => 'int', 'minuend' => 'int'],
                roles: [],
                tags: ['math'],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: SubtractMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'subtract',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: SubtractRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => 'array'],
                roles: [],
                tags: ['mathematic'],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: SumMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'sum',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: SumRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => 'array'],
                roles: [],
                tags: ['mathematic'],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: TestMethod::class,
                requestType: 'POST',
                summary: 'Test method summary',
                description: 'Test method description',
                ignoreInSwagger: false,
                methodName: 'test',
                allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                request: TestRequest::class,
                requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
                validators: ['id' => 'int', 'title' => 'string'],
                roles: ['ROLE_PENTESTER'],
                tags: ['test'],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: UpdateMethod::class,
                requestType: 'PUT',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'update',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: UpdateRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => 'array'],
                roles: [],
                tags: [],
                plainResponse: false,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
            new MethodSpec(
                methodClass: GetPictureMethod::class,
                requestType: 'POST',
                summary: '',
                description: 'get picture method',
                ignoreInSwagger: false,
                methodName: 'GetPicture',
                allParameters: [['name' => 'id', 'type' => 'integer']],
                requiredParameters: [['name' => 'id', 'type' => 'integer']],
                request: GetPictureRequest::class,
                requestSetters: ['id' => 'setId'],
                validators: ['id' => 'integer'],
                roles: [],
                tags: [],
                plainResponse: true,
                preProcessorExists: false,
                postProcessorExists: false,
            ),
        ];
    }
}
