# Bundle installation

1) Require the bundle as a dependency.

```bash
composer require otezvikentiy/json-rpc-api
```

2) Enable it in your application Kernel. (not required if using flex)

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

3) Create / update these config files
```yaml
# config/routes/ov_json_rpc_api.yaml
ov_json_rpc_api:
   resource: '@OVJsonRPCAPIBundle/config/routes/routes.yaml'
```

```yaml
# config/services.yaml
services:
    App\RPC\V1\:
        resource: '../src/RPC/V1/{*Method.php}'
        tags:
            - { name: ov.rpc.method, namespace: App\RPC\V1\, version: 1 }
```

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - localhost
        - api.localhost
        - *
    swagger:
        api_v1:
            api_version: '1'
            base_path: '%env(string:OV_JSON_RPC_API_BASE_URL)%'
            base_path_description: 'Production server (uses live data)'
            test_path: '%env(string:OV_JSON_RPC_API_TEST_URL)%'
            test_path_description: 'Sandbox server (uses test data)'
            base_path_variables:
                - {name: 'subdomain', value: 'api'}
                - {name: 'domain', value: 'localhost'}
                - {name: 'port', value: '31081'}
            test_path_variables:
                - {name: 'domain', value: 'test'}
            auth_token_name: 'X-AUTH-TOKEN'
            auth_token_test_value: '%env(string:OV_JSON_RPC_API_AUTH_TOKEN)%' #set blank for prod environment
            info:
                title: 'Some awesome api title here'
                description: 'Some description about your api here would be appreciated if you like'
                terms_of_service_url: 'https://terms_of_service_url.test/url'
                contact:
                    name: 'John Doe'
                    url: 'https://john-doe.test'
                    email: 'john.doe@john-doe.test'
                license: 'MIT license'
                licenseUrl: 'https://john-doe.test/mit-license'
```

> COMMENTS to ov_json_rpc_api.yaml
>
> base_path and test_path can have variables in them that you might want to change. For this,
> you can use base_path_variables and test_path_variables. In the example of base_path, you can specify
> it as ``{protocol}://{host}:{port}`` and accordingly in the base_path_variables section you will have
> ```
> base_path_variables:
>       - {name: 'protocol', value: 'https'}
>       - {name: 'host', value: 'some.domain'}
>       - {name: 'port', value: '100500'}
> ```
> This way you can adjust individual parts of base_path and test_path depending on your environments.

```dotenv
# .env
###> otezvikentiy/json-rpc-api ###
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
OV_JSON_RPC_API_BASE_URL=http://localhost
OV_JSON_RPC_API_AUTH_TOKEN=2f1f6aee7d994528fde6e47a493cc097
###< otezvikentiy/json-rpc-api ###
```