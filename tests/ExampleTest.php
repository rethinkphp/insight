<?php

namespace insight\tests;

use blink\http\Request;
use blink\http\Uri;

class ExampleTest extends TestCase
{
    public function testExample()
    {
        $response = $this->app->handleRequest(new Request(['uri' => new Uri('/'), 'method' => 'GET']));
        $this->assertEquals('Hello world, Blink.', $response->content());
    }
}
