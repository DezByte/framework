<?php

/**
 * @copyright  Frederic G. Østby
 * @license    http://www.makoframework.com/license
 */

namespace mako\cache\stores;

use mako\cache\stores\StoreInterface;
use mako\redis\Redis as RedisClient;

/**
 * Redis store.
 *
 * @author  Frederic G. Østby
 */

class Redis implements StoreInterface
{
	/**
	 * Redis client
	 * 
	 * @var \mako\redis\Redis
	 */

	protected $redis;

	/**
	 * Constructor.
	 * 
	 * @access  public
	 * @param   \mako\redis\Redis  $redis  Redis client
	 */

	public function __construct(RedisClient $redis)
	{
		$this->redis = $redis;
	}

	/**
	 * {@inheritdoc}
	 */

	public function put($key, $data, $ttl = 0)
	{
		$this->redis->set($key, (is_numeric($data) ? $data : serialize($data)));
		
		if($ttl !== 0)
		{
			$this->redis->expire($key, $ttl);
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */

	public function has($key)
	{
		return (bool) $this->redis->exists($key);
	}

	/**
	 * {@inheritdoc}
	 */

	public function get($key)
	{
		$data = $this->redis->get($key);

		return ($data === null) ? false : (is_numeric($data) ? $data : unserialize($data));
	}

	/**
	 * {@inheritdoc}
	 */

	public function remove($key)
	{
		return (bool) $this->redis->del($key);
	}

	/**
	 * {@inheritdoc}
	 */

	public function clear()
	{
		return (bool) $this->redis->flushdb();
	}
}