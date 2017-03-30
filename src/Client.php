<?php

namespace LinkORB\Component\Etcd;

use GuzzleHttp\Client as GuzzleClient;
use LinkORB\Component\Etcd\Interfaces\EtcdClientInterface;

class Client extends GuzzlessClient implements EtcdClientInterface
{
    const DEFAULT_SERVER = 'http://127.0.0.1:2379';

    /**
     * Client constructor.
     * @param string $server
     * @param array $options
     * @param string $version
     * @param string $root
     */
    public function __construct(
        $server = self::DEFAULT_SERVER,
        array $options = array(),
        $version = self::DEFAULT_API_VERSION,
        $root = self::DEFAULT_ROOT
    ) {
        $guzzleClient = new GuzzleClient(array_replace_recursive([
            'base_uri' => $server ? rtrim($server, '/') : self::DEFAULT_SERVER,
            'http_errors' => false
        ], $options));

        parent::__construct($guzzleClient, $version, $root);
    }
}
