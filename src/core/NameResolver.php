<?php

namespace Swoole;

use Swoole\NameResolver\Cluster;
use RuntimeException;

abstract class NameResolver
{
    private $filter_fn;

    abstract public function join(string $name, string $ip, int $port, array $options = []): bool;

    abstract public function leave(string $name, string $ip, int $port): bool;

    abstract public function getCluster(string $name): ?Cluster;

    public function withFilter(callable $fn): self
    {
        $this->filter_fn = $fn;
        return $this;
    }

    public function getFilter()
    {
        return $this->filter_fn;
    }

    public function hasFilter(): bool
    {
        return !empty($this->filter_fn);
    }

    /**
     * !!! The host MUST BE IP ADDRESS
     * @param $baseURL
     */
    protected function checkBaseURL(&$baseURL)
    {
        $info = parse_url($baseURL);
        if (empty($info['scheme']) or empty($info['host'])) {
            throw new RuntimeException("invalid baseURL{$baseURL}");
        }

        if (!filter_var($info['host'], FILTER_VALIDATE_IP)) {
            $ipAddr = gethostbyname($info['host']);
            $baseURL = str_replace($baseURL, $info['scheme'] . '://' . $info['host'],
                $info['scheme'] . '://' . $ipAddr);
        }
    }

    /**
     * return string: final result, non-empty string must be a valid IP address,
     * and an empty string indicates name lookup failed, and lookup operation will not continue.
     * return Cluster: has multiple nodes and failover is possible
     * return false or null: try another name resolver
     * @param string $name
     * @return Cluster|null|false|string
     */
    public function lookup(string $name)
    {
        if ($this->hasFilter() and ($this->getFilter())($name) !== true) {
            return null;
        }
        $cluster = $this->getCluster($name);
        // lookup failed, terminate execution
        if ($cluster == null) {
            return '';
        }
        // only one node, cannot retry
        if ($cluster->count() == 1) {
            return $cluster->pop();
        } else {
            return $cluster;
        }
    }
}
