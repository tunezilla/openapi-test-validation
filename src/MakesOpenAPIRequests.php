<?php

namespace TuneZilla\OpenAPITestValidation;

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

trait MakesOpenAPIRequests
{
    private ?string $openApiLastPath = null;
    private ?ValidatorBuilder $openApiValidator = null;
    private bool $ignoreValidationForNextRequest = false;

    private function openAPI(string $path)
    {
        if ($this->openApiLastPath !== $path) {
            try {
                $this->openApiValidator = (new ValidatorBuilder)->fromYamlFile($path);
                $this->openApiValidator->getRequestValidator(); // trigger errors if path is bad
                $this->openApiLastPath = $path;
            } catch (\Throwable $ex) {
                throw new OpenAPIConfigurationException(
                    "Unable to load OpenAPI at path {$path}: {$ex->getMessage()}",
                    $ex->getCode(),
                    $ex
                );
            }
        }
        return $this;
    }

    private function ignoreNextOpenAPIRequest()
    {
        $this->ignoreValidationForNextRequest = true;
        return $this;
    }

    public function call(
        $method,
        $uri,
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null
    ) {
        if ($this->openApiValidator === null) {
            throw new OpenAPIConfigurationException('Must set openAPI($path) before performing any calls');
        }

        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));

        $request = $psrHttpFactory->createRequest(SymfonyRequest::create(
            $this->prepareUrlForRequest($uri), $method, $parameters,
            $cookies, $files, array_replace($this->serverVariables, $server), $content
        ));

        $operationAddress = null;

        if ($this->ignoreValidationForNextRequest) {
            $this->ignoreValidationForNextRequest = false;
            $operationAddress = new OperationAddress($request->getUri()->getPath(), strtolower($request->getMethod()));
        } else {
            try {
                $operationAddress = $this->openApiValidator
                    ->getRequestValidator()
                    ->validate($request);
            } catch (\Throwable $ex) {
                throw new OpenAPIRequestException(
                    "Validation failed for OpenAPI request: {$ex->getMessage()}",
                    $ex->getCode(),
                    $ex
                );
            }
        }

        $response = parent::call(
            $method,
            $uri,
            $parameters,
            $cookies,
            $files,
            $server,
            $content
        );

        try {
            $this->openApiValidator
                ->getResponseValidator()
                ->validate($operationAddress, $psrHttpFactory->createResponse($response->baseResponse));
        } catch (\Throwable $ex) {
            $details = [
                'body' => $response->getContent(),
            ];

            if ($ex instanceof InvalidBody) {
                $innerEx = $ex->getPrevious();
                if ($innerEx instanceof SchemaMismatch) {
                    $breadcrumbs = $innerEx->dataBreadCrumb();
                    if ($breadcrumbs !== null) {
                        $details['crumbs'] = json_encode($breadcrumbs->buildChain(), JSON_PRETTY_PRINT);
                    }
                }
            }

            $detailString = '';
            foreach ($details as $key => $value) {
                $detailString .= "{$key}: {$value}\n";
            }

            throw new OpenAPIResponseException(
                "OpenAPI Response Validation Failed:\n{$detailString}",
                $ex->getCode(),
                $ex
            );
        }

        return $response;
    }
}
