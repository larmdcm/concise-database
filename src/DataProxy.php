<?php

namespace Concise\Database;

use ArrayAccess;

class DataProxy implements ArrayAccess
{
	/**
	 * 数据
	 * @var array
	 */
	protected $data = [];

	/**
	 * 初始化
	 * @param array $data 
	 */
	public function __construct ($data = [])
	{
		$this->data = $data;
	}

	/**
	 * 获取数据是否为空
	 * @return boolean
	 */
	public function isEmpty ()
	{
		return empty($this->data);
	}

	/**
	 * 返回array数组
	 * @return array
	 */
	public function toArray ()
	{
		return $this->isEmpty() ? [] : $this->data;
	}

	// getattr and setattr
	public function __get ($key)
	{
		return $this->data[$key];
	}

	public function __set ($key,$value)
	{
		$this->data[$key] = $value;
	}

    // ArrayAccess
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}