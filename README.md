# OtezVikentiy Symfony Json RPC API Bundle

The bundle allows you to quickly and conveniently create JSON RPC API applications based on the Symfony framework.

---

## Features


- [x] easy api versioning
- [x] easy bundle installation
- [x] compatible with attributes
- [x] compatible with POST, GET, PUT, PATCH, DELETE requests
- [x] fully compatible with https://www.jsonrpc.org/specification
- [x] swagger openapi out of the box
- [x] preprocessors and postprocessors

github: https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle

---

## Bundle installation


[see](./docs/installation.md) how to easily install bundle.

---

## Examples

During the installation process, we defined the `src/RPC/V1/{*Method.php}` directory in the services and marked with
tags in it all the classes ending in `*Method.php` - these will be our API endpoints.

|                           link                            |                                                                      description                                                                      |                                files list                                | curl  |
|:---------------------------------------------------------:|:-----------------------------------------------------------------------------------------------------------------------------------------------------:|:------------------------------------------------------------------------:|:-----:|
|          [see example](./docs/examples/base.md)           |                                                                 Base easiest example.                                                                 |             Request.php, Response.php, GetProductsMethod.php             |``curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1``|
| [see example](./docs/examples/pre-and-post-processors.md) |                                                       Example with pre- and post- processors.                                                        |   Request.php, Response.php, GetProductsMethod.php, AbstractMethod.php   |``curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1``|
|    [see example](./docs/examples/array_of_objects.md)     |                                 Example when you need to give in response not single object, but a number of objects.                                 |      Request.php, Response.php, GetProductsMethod.php, Product.php       |``curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1``|
|     [see example](./docs/examples/simple_response.md)     | Sometimes you may need to return from API not only a json response, but a picture or a document for example. In such a case you can use this example. | Request.php, ErrorResponse.php, PlainResponse.php, GetProductsMethod.php |``curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1``|

---

## Swagger

If you wish to generate openapi swagger yaml file - then run this command:

```bash
bin/console ov:swagger:generate
```

It would generate a swagger file ``public/openapi/api_v1.yaml`` which you can use in your swagger instance.

[see](./docs/swagger/tags.md) example of how to combine multiple endpoints with tags

[see](./docs/swagger/scalar.md) example how to define default, format and example for scalar properties of response

[see](./docs/swagger/array.md) example how to describe array properties of response

---

## Security

Initially, two implementation options are provided and tested, described below, but you are free to 
connect any other software solutions to your taste and color.

[Auth via lexik jwt token bundle](./docs/security/jwt_bundle.md)

[Auth via self-written system](./docs/security/self_made_token.md)

You may need to add a role model to restrict user access.
You always have the option to implement your own version, but there is also a
built-in implementation based on the simplest [Symfony Security](https://symfony.com/doc/current/security.html) version.

[Built-in roles usage example](./docs/security/roles.md)