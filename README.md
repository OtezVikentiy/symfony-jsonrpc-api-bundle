Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require otezvikentiy/pass-generator-bundle
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require otezvikentiy/pass-generator-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    PassGeneratorBundle\PassGeneratorBundle::class => ['all' => true],
];
```

Настройки можно положить в config/packages/password_generator.yaml


password_generator:
    passwordLength: 10 #значение длины пароля
    numbers: true #использовать числа или нет
    upperCase: true  #использовать латинские символы верхнего регистра или нет
    lowerCase: true #использовать латинские символы ниднего регистра или нет
    specialChars: false #использовать спецсимволы или нет
    passContentsInterface: App\Service\PassContents #можно реализовать собственный PassGeneratorBundle\PassContentsInterface, что позволит перечислить собственные символы для генерации паролей