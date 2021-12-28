<?php
/*
 * PSX is an open source PHP framework to develop RESTful APIs.
 * For the current version and information visit <https://phpsx.org>
 *
 * Copyright 2010-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Engine\Swoole;

use PSX\Engine\DispatchInterface;
use PSX\Engine\EngineInterface;
use PSX\Http\Request;
use PSX\Http\Server\ResponseFactory;
use PSX\Http\Stream\StringStream;
use PSX\Uri\Uri;
use Swoole\Http as SwooleHttp;

/**
 * Uses the Swoole HTTP server
 *
 * @see     https://github.com/swoole/swoole-src
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class Engine implements EngineInterface
{
    private string $ip;
    private int $port;
    private ?array $options;

    public function __construct(string $ip = '0.0.0.0', int $port = 8080, ?array $options = null)
    {
        $this->ip      = $ip;
        $this->port    = $port;
        $this->options = $options;
    }

    public function serve(DispatchInterface $dispatch): void
    {
        $server = new SwooleHttp\Server($this->ip, $this->port);

        if (!empty($this->options)) {
            $server->set($this->options);
        }

        $server->on('request', function (SwooleHttp\Request $swooleRequest, SwooleHttp\Response $swooleResponse) use ($dispatch) {
            $this->process($swooleRequest, $swooleResponse, $dispatch);
        });

        $server->start();
    }

    private function process(SwooleHttp\Request $swooleRequest, SwooleHttp\Response $swooleResponse, DispatchInterface $dispatch)
    {
        $request  = new Request($swooleRequest->server['path_info'], $swooleRequest->server['request_method'], $swooleRequest->header);
        $response = (new ResponseFactory())->createResponse();

        // read body
        if (in_array($swooleRequest->server['request_method'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $request->setBody(new StringStream($swooleRequest->rawContent()));
        }

        $response = $dispatch->route($request, $response);

        // send response
        $swooleResponse->status($response->getStatusCode() ?: 200);

        $headers = $response->getHeaders();
        foreach ($headers as $name => $value) {
            $swooleResponse->header($name, implode(', ', $value));
        }

        $swooleResponse->end($response->getBody()->__toString());
    }
}
