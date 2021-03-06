<?php

namespace Flat3\Lodata\Tests;

use Flat3\Lodata\Helper\Url;

class Request
{
    public $headers = [];
    public $path = '/odata';
    public $query = [];
    public $body = null;
    public $method = \Illuminate\Http\Request::METHOD_GET;

    public static function factory()
    {
        $request = new self();
        $request->json();
        return $request;
    }

    public function header($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function unsetHeader($key): self
    {
        unset($this->headers[$key]);

        return $this;
    }

    public function query($key, $value)
    {
        $this->query[$key] = $value;
        return $this;
    }

    public function metadata($type)
    {
        $this->accept('application/json;odata.metadata='.$type);
        return $this;
    }

    public function preference($key, $value)
    {
        $this->header('prefer', $key.'='.$value);
        return $this;
    }

    public function path($path, $withPrefix = true)
    {
        $this->path = $path;

        if ($withPrefix) {
            $this->path = '/odata'.$this->path;
        }

        return $this;
    }

    public function filter($filter)
    {
        $this->query('$filter', $filter);
        return $this;
    }

    public function select($select)
    {
        $this->query('$select', $select);
        return $this;
    }

    public function uri()
    {
        return Url::http_build_url([
            'query' => http_build_query($this->query),
            'path' => $this->path,
        ]);
    }

    public function headers()
    {
        return $this->headers;
    }

    public function method($method): self
    {
        $this->method = $method;
        return $this;
    }

    public function post(): self
    {
        return $this->method(\Illuminate\Http\Request::METHOD_POST);
    }

    public function multipart(string $body): self
    {
        $this->header('accept', 'multipart/mixed');
        $this->body = str_replace("\n", "\r\n", $body);

        return $this;
    }

    public function body($body): self
    {
        if (is_array($body)) {
            $this->header('content-type', 'application/json');
            $body = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $this->body = $body;
        return $this;
    }

    public function patch(): self
    {
        return $this->method(\Illuminate\Http\Request::METHOD_PATCH);
    }

    public function put(): self
    {
        return $this->method(\Illuminate\Http\Request::METHOD_PUT);
    }

    public function delete(): self
    {
        return $this->method(\Illuminate\Http\Request::METHOD_DELETE);
    }

    public function xml()
    {
        $this->header('accept', 'application/xml');
        return $this;
    }

    public function json(): self
    {
        $this->header('accept', 'application/json');
        return $this;
    }

    public function text()
    {
        $this->header('accept', 'text/plain');
        return $this;
    }

    public function accept($accept)
    {
        $this->header('accept', $accept);
        return $this;
    }
}
