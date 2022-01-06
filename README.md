# OpenAPI Request and Response Validation for Laravel Tests

This package allows you to perform OpenAPI request and response validation in Laravel tests while using
the normal `$this->get()`, `$this->json()`, and `$this->call()` functions from the `MakesHttpRequests` trait.

This package does not currently have a Laravel version restriction. It is tested with Laravel 8.

## Installation

`composer require --dev tunezilla/openapi-test-validation`

## Gotchas

The response body is consumed using [symfony/psr-http-message-bridge](https://github.com/symfony/psr-http-message-bridge/blob/2bead2266bbff6b138f2c8967e609d0c4e5307b8/Factory/PsrHttpFactory.php#L135) in order to validate that it matches the OpenAPI schema.

If you're testing responses that only send content once (like a [Symfony StreamedResponse](https://github.com/symfony/http-foundation/blob/c0bf3e7fdca5a396464148eb8a63356028a9cdb9/StreamedResponse.php#L99))
but try to retrieve this content in your test: OpenAPI validation has already occurred, the content has already been read, and you will receive an empty body.

Regular non-streamed responses are not affected by this issue.

## Usage

1. Add the `MakesOpenAPIRequests` trait to your `TestCase`
2. Before sending a request, call `$this->openAPI('./path/to/your/openapi.yaml')` (chainable)

### Single Schema

```php
<?php

namespace Tests\Feature;

use TuneZilla\OpenAPITestValidation\MakesOpenAPIRequests;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use MakesOpenAPIRequests;

    public function testShow()
    {
        $this->openAPI(resource_path('openapi.yaml'))
            ->json('GET', '/api/settings')
            ->assertSuccessful();
    }
}
```

### Setup Once

```php
<?php

namespace Tests\Feature;

use TuneZilla\OpenAPITestValidation\MakesOpenAPIRequests;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use MakesOpenAPIRequests;
    
    public function setUp()
    {
        parent::setUp();
        // if all requests in this testcase use the same schema, you can configure it once in `setUp`
        // see https://phpunit.readthedocs.io/en/9.3/fixtures.html for information about `setUp`
        $this->openAPI(resource_path('openapi.yaml'));
    }

    public function testShow()
    {
        $this->json('GET', '/api/settings')
            ->assertSuccessful();
    }
}
```

### Multiple Schemas

```php
<?php

namespace Tests\Feature;

use TuneZilla\OpenAPITestValidation\MakesOpenAPIRequests;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use MakesOpenAPIRequests;

    public function testShow()
    {
        // if your requests use different schemas, you can configure them as needed
        
        // this one uses settings/openapi.yaml
        $this->openAPI(resource_path('settings/openapi.yaml'))
            ->json('GET', '/api/settings')
            ->assertSuccessful();
            
        // this one uses other/openapi.yaml
        $this->openAPI(resource_path('other/openapi.yaml'))
            ->json('GET', '/api/other')
            ->assertSuccessful();
    }
}
```

### Sending Invalid Request

```php
<?php

namespace Tests\Feature;

use TuneZilla\OpenAPITestValidation\MakesOpenAPIRequests;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use MakesOpenAPIRequests;

    public function testInvalidRequest()
    {
        // you can use `ignoreNextOpenAPIRequest` to allow invalid requests to pass through to your backend.
        // this is useful if you're making sure 422 validation errors are thrown:
        $this->openAPI(resource_path('openapi.yaml'))
            ->ignoreNextOpenAPIRequest()
            ->json('GET', '/api/settings?invalid-param=12345')
            ->assertJsonValidationError(['invalid-param']);
    }
}
```
