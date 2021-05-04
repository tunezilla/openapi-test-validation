<?php

namespace TuneZilla\OpenAPITestValidation\Test;

use Orchestra\Testbench\TestCase;
use TuneZilla\OpenAPITestValidation\MakesOpenAPIRequests;
use TuneZilla\OpenAPITestValidation\OpenAPIConfigurationException;
use TuneZilla\OpenAPITestValidation\OpenAPIRequestException;
use TuneZilla\OpenAPITestValidation\OpenAPIResponseException;

class MakesOpenAPIRequestsTest extends TestCase
{
    use MakesOpenAPIRequests;

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    protected function defineRoutes($router)
    {
        $router->get('/api/foo', [DummyController::class, 'index']);
    }

    public function testLoadMissingYAML()
    {
        $this->expectException(OpenAPIConfigurationException::class);
        $this->openAPI(__DIR__);
    }

    public function testMakeRequestBeforeLoadingYAML()
    {
        $this->expectException(OpenAPIConfigurationException::class);
        $this->json('GET', '/api/foo');
    }

    public function testMakeValidRequest()
    {
        $this->openAPI(__DIR__ . '/schema.yaml')
            ->json('GET', '/api/foo')
            ->assertSuccessful();
    }

    public function testMakeRequestToMissingRoute()
    {
        $this->expectException(OpenAPIRequestException::class);
        $this->openAPI(__DIR__ . '/schema.yaml')
            ->json('POST', '/api/foo');
    }

    public function testMakeRequestWithInvalidParameters()
    {
        $this->expectException(OpenAPIRequestException::class);
        $this->openAPI(__DIR__ . '/schema.yaml')
            ->json('GET', '/api/foo?bad_enum=281-330-8004');
    }

    public function testMakeRequestWithInvalidResponseEnum()
    {
        $this->expectException(OpenAPIResponseException::class);
        $this->openAPI(__DIR__ . '/schema.yaml')
            ->json('GET', '/api/foo?bad_enum=true');
    }

    public function testMakeRequestWithInvalidResponseCode()
    {
        $this->expectException(OpenAPIResponseException::class);
        $this->openAPI(__DIR__ . '/schema.yaml')
            ->json('GET', '/api/foo?bad_code=true');
    }
}
