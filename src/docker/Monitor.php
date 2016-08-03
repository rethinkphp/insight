<?php

namespace insight\docker;

use blink\core\Object;
use insight\EventBus;

/**
 * Class Monitor
 *
 * @package insight
 */
class Monitor extends Object
{
    /**
     * The host of docker & swarm's api endpoint.
     *
     * @var string
     */
    public $host = '127.0.0.1';

    /**
     * The port of docker & swarm's api endpoint.
     *
     * @var string
     */
    public $port = 4243;

    protected $headerReceiced = false;

    protected $retryTimeout = 2;

    protected $bus;


    public function __construct(EventBus $bus, array $config)
    {
        $this->bus = $bus;

        parent::__construct($config);
    }

    /**
     * Start the monitor
     */
    public function start()
    {
        $this->createConnection();
    }

    protected function createConnection()
    {
        $this->headerReceiced = false;

        $client = new \swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

        $client->on('connect', [$this, 'onConnect']);
        $client->on('receive', [$this, 'onReceive']);
        $client->on('error', [$this, 'onError']);
        $client->on('close', [$this, 'onClose']);

        $client->connect($this->host, $this->port);
    }

    public function onConnect($client)
    {
        logger()->info("connected to docker host {$this->host}:{$this->port}");

        $client->send("GET /events HTTP/1.1\r\n\r\n");
    }

    public function onReceive($client, $data)
    {
        if (!$this->headerReceiced) {
            $this->headerReceiced = true;
            return;
        }

        $message = $this->parseChunk($data);
        if (!$message) {
            return;
        }

        logger()->info('received message: ' . json_encode($message));

        $this->bus->dispatch($this->resolveEventName($message), $message);
    }

    protected function resolveEventName($message)
    {
        if (isset($message['Type'], $message['Action'])) {
            return 'docker.' . $message['Type'] . '.' . $message['Action'];
        }

        return 'docker.container.' . $message['status'];
    }

    protected function parseChunk($data)
    {
        list($len, $payload) = explode("\r\n", substr($data, 0, -2), 2);

        $len = base_convert($len, 16, 10);

        if ($len != strlen($payload)) {
            logger()->error('error in parsing http chunks');
            return false;
        }

        return json_decode($payload, true);
    }

    public function onError($client)
    {
        logger()->error('error in http_client');
    }

    public function onClose($client)
    {
        logger()->warning("the connection to {$this->host}:{$this->port} closed");

        // TODO retry
    }
}
