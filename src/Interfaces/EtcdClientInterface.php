<?php

namespace LinkORB\Component\Etcd\Interfaces;

interface EtcdClientInterface
{
    /**
     * Retrieve the value of a key
     *
     * @param string $key
     * @param array $flags the extra query params
     * @return string the value of the key.
     */
    public function get($key, array $flags = null);

    /**
     * Set the value of a key
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @param array $condition
     * @return array
     */
    public function set($key, $value, $ttl = null, $condition = array());

    /**
     * Update an existing key with a given value.
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @param array $condition The extra condition for updating
     * @return array $body
     */
    public function update($key, $value, $ttl = 0, $condition = array());

    /**
     * Remove a key
     *
     * @param string $key
     * @return array
     */
    public function rm($key);

    /**
     * Retrieve the value of a key
     *
     * @param string $key
     * @param array $flags the extra query params
     * @return array
     */
    public function getNode($key, array $flags = null);

    /**
     * Get all key-value pair that the key is not directory.
     *
     * @param string $root
     * @param boolean $recursive
     * @param string $key
     * @return array
     */
    public function getKeysValue($root = '/', $recursive = true, $key = null);

    /**
     * Make a new directory
     *
     * @param string $key
     * @param int $ttl
     * @return array $body
     */
    public function mkdir($key, $ttl = 0);

    /**
     * Retrieve a directory
     *
     * @param string $key
     * @param boolean $recursive
     * @return mixed
     */
    public function listDir($key = '/', $recursive = false);
}
