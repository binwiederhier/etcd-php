<?php
namespace LinkORB\Component\Etcd;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use LinkORB\Component\Etcd\Exception\EtcdException;
use LinkORB\Component\Etcd\Exception\KeyExistsException;
use LinkORB\Component\Etcd\Exception\KeyNotFoundException;
use Psr\Http\Message\ResponseInterface;
use RecursiveArrayIterator;

class GuzzlessClient
{
    /**
     * Default settings
     */
    const DEFAULT_ROOT = '';
    const DEFAULT_API_VERSION = 'v2';

    /**
     * @var ClientInterface
     */
    protected $http;

    /**
     * @var string
     */
    protected $apiVersion;

    /**
     * @var string
     */
    protected $root;

    /**
     * @var array
     */
    protected $dirs;

    /**
     * @var array
     */
    protected $values;

    /**
     * Client constructor.
     * @param ClientInterface $httpClient
     * @param string $version
     * @param string $root
     * @throws EtcdException
     */
    public function __construct(
        ClientInterface $httpClient,
        $version = self::DEFAULT_API_VERSION,
        $root = self::DEFAULT_ROOT
    ) {
        $this->setRoot($root);
        $this->apiVersion = trim($version, '/');

        if (is_null($httpClient->getConfig('base_uri'))) {
            throw new EtcdException('Base URI not set at HTTP Client', 205);
        }
        $this->http = $httpClient;
    }

    /**
     * Set the default root directory. the default is `/`
     * If the root is others e.g. /linkorb when you set new key,
     * or set dir, all of the key is under the root
     * e.g.
     * <code>
     *    $client->setRoot('/linkorb');
     *    $client->set('key1, 'value1');
     *    // the new key is /linkorb/key1
     * </code>
     * @param string $root
     * @return self
     */
    public function setRoot($root)
    {
        $this->root = trim($root, '/');
        return $this;
    }

    /**
     * Build key space operations
     * @param string $key
     * @return string
     */
    private function buildKeyUri($key)
    {
        return str_replace(
            '//',
            '/',
            sprintf(
                '/%s/keys/%s/%s',
                $this->apiVersion,
                $this->root,
                trim($key, '/')
            )
        );
    }

    /**
     * Do a server request
     * @param string $uri
     * @return mixed
     */
    public function doRequest($uri)
    {
        $response = $this->http->request('GET', $uri);
        $data = $response->getBody()->getContents();
        return $data;
    }

    /**
     * Set the value of a key
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @param array $condition
     * @return array
     */
    public function set($key, $value, $ttl = null, $condition = array())
    {
        $data = array('value' => $value);

        if ($ttl) {
            $data['ttl'] = $ttl;
        }

        $response = $this->http->request('PUT', $this->buildKeyUri($key), array(
            RequestOptions::HEADERS => $this->getHeaders(),
            RequestOptions::BODY => http_build_query($data),
            RequestOptions::QUERY => $condition
        ));

        $body = $this->getBody($response);
        return $body;
    }

    /**
     * Retrieve the value of a key
     * @param string $key
     * @param array $flags the extra query params
     * @return array
     * @throws KeyNotFoundException
     */
    public function getNode($key, array $flags = null)
    {
        $data = array();
        if ($flags) {
            $data = array(
                RequestOptions::QUERY => $flags
            );
        }

        $response = $this->http->request('GET', $this->buildKeyUri($key), $data);

        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }
        return $body['node'];
    }

    /**
     * Retrieve the value of a key
     * @param string $key
     * @param array $flags the extra query params
     * @return string the value of the key.
     * @throws KeyNotFoundException
     */
    public function get($key, array $flags = null)
    {
        $node = $this->getNode($key, $flags);
        return $node['value'];
    }

    /**
     * make a new key with a given value
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return array $body
     * @throws KeyExistsException
     */
    public function mk($key, $value, $ttl = 0)
    {
        $body = $request = $this->set(
            $key,
            $value,
            $ttl,
            array('prevExist' => 'false')
        );

        if (isset($body['errorCode'])) {
            throw new KeyExistsException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * make a new directory
     *
     * @param string $key
     * @param int $ttl
     * @return array $body
     * @throws KeyExistsException
     */
    public function mkdir($key, $ttl = 0)
    {
        $data = array('dir' => 'true');

        if ($ttl) {
            $data['ttl'] = $ttl;
        }
        $response = $this->http->request(
            'PUT',
            $this->buildKeyUri($key),
            array(
                RequestOptions::HEADERS => $this->getHeaders(),
                RequestOptions::BODY => http_build_query($data),
                RequestOptions::QUERY => array('prevExist' => 'false')
            )
        );

        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new KeyExistsException($body['message'], $body['errorCode']);
        }
        return $body;
    }


    /**
     * Update an existing key with a given value.
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @param array $condition The extra condition for updating
     * @return array $body
     * @throws KeyNotFoundException
     */
    public function update($key, $value, $ttl = 0, $condition = array())
    {
        $extra = array('prevExist' => 'true');

        if ($condition) {
            $extra = array_merge($extra, $condition);
        }
        $body = $this->set($key, $value, $ttl, $extra);
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }
        return $body;
    }

    /**
     * Update directory
     * @param string $key
     * @param int $ttl
     * @return array $body
     * @throws EtcdException
     */
    public function updateDir($key, $ttl)
    {
        if (!$ttl) {
            throw new EtcdException('TTL is required', 204);
        }

        $condition = array(
            'dir' => 'true',
            'prevExist' => 'true'
        );

        $response = $this->http->request(
            'PUT',
            $this->buildKeyUri($key),
            array(
                RequestOptions::HEADERS => $this->getHeaders(),
                RequestOptions::BODY => http_build_query(['ttl' => intval($ttl)]),
                RequestOptions::QUERY => $condition
            )
        );
        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }
        return $body;
    }


    /**
     * remove a key
     * @param string $key
     * @return array
     * @throws EtcdException
     */
    public function rm($key)
    {
        $response = $this->http->request('DELETE', $this->buildKeyUri($key));
        $body = $this->getBody($response);

        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * Removes the key if it is directory
     * @param string $key
     * @param boolean $recursive
     * @return mixed
     * @throws EtcdException
     */
    public function rmdir($key, $recursive = false)
    {
        $query = array('dir' => 'true');

        if ($recursive === true) {
            $query['recursive'] = 'true';
        }
        $response = $this->http->request(
            'DELETE',
            $this->buildKeyUri($key),
            array(
                RequestOptions::QUERY => $query
            )
        );
        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }
        return $body;
    }

    /**
     * Retrieve a directory
     * @param string $key
     * @param boolean $recursive
     * @return mixed
     * @throws KeyNotFoundException
     */
    public function listDir($key = '/', $recursive = false)
    {
        $query = array();
        if ($recursive === true) {
            $query['recursive'] = 'true';
        }
        $response = $this->http->request(
            'GET',
            $this->buildKeyUri($key),
            array(
                RequestOptions::QUERY => $query
            )
        );
        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }
        return $body;
    }

    /**
     * Retrieve a directories key
     * @param string $key
     * @param boolean $recursive
     * @return array
     * @throws EtcdException
     */
    public function ls($key = '/', $recursive = false)
    {
        $this->values = array();
        $this->dirs = array();

        try {
            $data = $this->listDir($key, $recursive);
        } catch (EtcdException $e) {
            throw $e;
        }

        $iterator = new RecursiveArrayIterator($data);
        return $this->traversalDir($iterator);
    }

    /**
     * Traversal the directory to get the keys.
     * @param RecursiveArrayIterator $iterator
     * @return array
     */
    private function traversalDir(RecursiveArrayIterator $iterator)
    {
        $key = '';
        while ($iterator->valid()) {
            if ($iterator->hasChildren()) {
                $this->traversalDir($iterator->getChildren());
            } else {
                if ($iterator->key() == 'key' && ($iterator->current() != '/')) {
                    $this->dirs[] = $key = $iterator->current();
                }

                if ($iterator->key() == 'value') {
                    $this->values[$key] = $iterator->current();
                }
            }
            $iterator->next();
        }
        return $this->dirs;
    }

    /**
     * Get all key-value pair that the key is not directory.
     * @param string $root
     * @param boolean $recursive
     * @param string $key
     * @return array
     */
    public function getKeysValue($root = '/', $recursive = true, $key = null)
    {
        $this->ls($root, $recursive);
        if (isset($this->values[$key])) {
            return $this->values[$key];
        }
        return $this->values;
    }

    /**
     * create a new directory with auto generated id
     *
     * @param string $dir
     * @param int $ttl
     * @return array $body
     * @throws EtcdException
     */
    public function mkdirWithInOrderKey($dir, $ttl = 0)
    {
        $data = array(
            'dir' => 'true'
        );

        if ($ttl) {
            $data['ttl'] = $ttl;
        }
        $response = $this->http->request(
            'POST',
            $this->buildKeyUri($dir),
            [
                RequestOptions::HEADERS => $this->getHeaders(),
                RequestOptions::BODY => http_build_query($data)
            ]
        );
        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }
        return $body;
    }

    /**
     * create a new key in a directory with auto generated id
     *
     * @param string $dir
     * @param string $value
     * @param int $ttl
     * @param array $condition
     * @throws EtcdException
     */
    public function setWithInOrderKey($dir, $value, $ttl = 0, $condition = array())
    {
        $data = array('value' => $value);

        if ($ttl) {
            $data['ttl'] = $ttl;
        }

        $response = $this->http->request('POST', $this->buildKeyUri($dir), array(
            RequestOptions::HEADERS => $this->getHeaders(),
            RequestOptions::BODY => http_build_query($data),
            RequestOptions::QUERY => $condition
        ));
        $body = $this->getBody($response);
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        return $this->getHeaders();
    }

    /**
     * @param ResponseInterface $response
     * @return mixed
     */
    protected function getBody(ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }
}
