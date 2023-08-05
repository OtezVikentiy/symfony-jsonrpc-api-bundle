# Bundle installation

Require the bundle as a dependency.

```bash
$ composer require otezvikentiy/json-rpc-api
```

Enable it in your application Kernel.

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

Import routing and configure services section.

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

---

« [README](../README.md) • [Test-Drive](2-test-drive.md) »