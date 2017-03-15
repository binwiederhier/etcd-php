<?php

namespace LinkORB\Tests\Component\Etcd;

use GuzzleHttp\ClientInterface;
use LinkORB\Component\Etcd\GuzzlessClient;
use \PHPUnit_Framework_TestCase;
use \PHPUnit_Framework_MockObject_MockObject as Mock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class GuzzlessClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param Mock|null $client
     * @param string $version
     * @param string $root
     * @return GuzzlessClient
     */
    private function getClientMock(
        Mock $client = null,
        $version = GuzzlessClient::DEFAULT_API_VERSION,
        $root = GuzzlessClient::DEFAULT_ROOT
    ) {
        if (!$client) {
            /** @var ClientInterface $client */
            $client = $this->getMockBuilder(ClientInterface::class)
                ->disableOriginalConstructor()
                ->getMock();
        }

        $client->expects($this->at(0))
            ->method('getConfig')
            ->with('base_uri')
            ->willReturn('');

        return new GuzzlessClient($client, $version, $root);
    }

    /**
     * @param string $root
     * @param string $expectedRoot
     * @dataProvider rootsProvider
     */
    public function testSetRoot($root, $expectedRoot)
    {
        $client = $this->getClientMock();
        $client->setRoot($root);

        $reflector = new \ReflectionClass(get_class($client));
        $property = $reflector->getProperty('root');
        $property->setAccessible(true);

        $this->assertEquals($property->getValue($client), $expectedRoot);
    }

    /**
     * @return array
     */
    public function rootsProvider()
    {
        return [
            ['/', ''],
            ['//', ''],
            ['/test/', 'test'],
        ];
    }

    public function testDoRequest()
    {
        $data = 'data_response';
        $uri = 'http.org';

        $client = $this->getMockBuilder(ClientInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('request')
            ->with('GET', $uri)
            ->willReturn($this->mockResponse($data));

        $etcdClient = $this->getClientMock($client);

        $this->assertEquals($data, $etcdClient->doRequest($uri));
    }

    /**
     * @param $data
     * @return Mock
     */
    private function mockResponse($data)
    {
        $streamMock = $this->getMockBuilder(StreamInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $streamMock->expects($this->once())
            ->method('getContents')
            ->willReturn($data);

        $response = $this->getMockBuilder(ResponseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($streamMock);

        return $response;
    }
}