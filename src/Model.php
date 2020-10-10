<?php

namespace Concise\Database;

use Concise\Database\Concern\Attribute;
use Concise\Database\Concern\Relation;
use Concise\Database\Concern\TimeStamp;
use Concise\Database\Concern\Conversion;

abstract class Model implements \ArrayAccess,\JsonSerializable
{
	use Attribute,
		Relation,
		TimeStamp,
		Conversion;

	/**
	 * 主键key
	 * @var string
	 */
	protected $primaryKey = 'id';

	/**
	 * 数据表名称
	 * @var string
	 */
	protected $name;

	/**
	 * 数据表
	 * @var string
	 */
	protected $table;

	/**
	 * 数据
	 * @var array
	 */
	protected $data = [];

	/**
	 * Db
	 * @var Db
	 */
	protected $db;

	/**
	 * 是否更新数据
	 * @var boolean
	 */
	protected $isUpdate = false;

	/**
	 * 是否更新数据
	 * @var boolean
	 */
	protected $isSavePrimaryKey = true;

	/**
	 * 软删除字段
	 * @var string
	 */
	protected $deleteTime = 'delete_time';

	/**
	 * 软删除字段类型
	 * @var string
	 */
	protected $deleteTimeFieldType = 'datetime';

	/**
	 * 默认软删除
	 * @var string
	 */
	protected $defaultSoftDelete;

	/**
	 * 关联模型数据
	 * @var array
	 */
	protected $relations = [];

	/**
	 * 初始化
	 * @param array $data
	 * @param boolean $isUpdate
	 */
	public function __construct ($data = [],$isUpdate = false)
	{
		$this->data = $data;

		if (!$this->name) {
			$this->name = $this->uncamelize(basename(
				str_replace("\\","/",get_class($this))
			));
		}

		$this->isUpdate = $isUpdate;

		$this->getDb()->setPrimaryKey($this->primaryKey);
	}

	/**
	 * set
	 * @param string $name  
	 * @param string $value 
	 * @return void
	 */
	public function __set ($name,$value)
	{
		$this->setAttr($name,$value);
	}

	/**
	 * get
	 * @param  string $name 
	 * @return mixed       
	 */
	public function __get ($name)
	{
		return $this->getAttr($name);
	}

	/**
	 * 新增数据
	 * @param  array $data 
	 * @return Model   
	 */
	public static function create ($data = [])
	{
		$model = new static();
		$model->save($data);
		return $model;
	}

	/**
	 * 新增数据返回自增id
	 * @param  array $data 
	 * @return Model 
	 */
	public static function insertGetId ($data = [])
	{
		$model = static::create($data);
		return $model->{$model->primaryKey};
	}

	/**
	 * 新增或更新数据
	 * @param  array $data     
	 * @param  array $condtion 
	 * @return boolean      
	 */
	public function save ($data = [],$condtion = [])
	{
		if (!empty($data)) {
			array_walk($data,function ($value,$key) {
				$this->setAttr($key,$value);
			});
		}
		
		$this->checkTimeStampWrite();

		$result = $this->isUpdate ? $this->updateData($this->data,$condtion) : $this->insertData($this->data);

		if (!$this->isUpdate) {
			$this->isUpdate = true;
		}

		return boolval($result);
	}

	/**
	 * 新增数据
	 * @param  array $data 
	 * @return integer   
	 */
	protected function insertData ($data)
	{
		$result = $this->getDb()->insertGetId($data);

		if (!empty($result) && $this->isSavePrimaryKey) {
			$data[$this->primaryKey] = $result;
		}

		$this->data = $data;
		
		return $result;
	}

	/**
	 * 修改数据
	 * @param  array $data     
	 * @param  array $condtion 
	 * @return bool       
	 */
	protected function updateData ($data,$condtion = [])
	{
		$this->data = $data;
		if (isset($data[$this->primaryKey]) 
			&& !empty($data[$this->primaryKey])
			&& !isset($condtion[$this->primaryKey])) {
			$condtion[$this->primaryKey] = $data[$this->primaryKey];
		}
		return $this->getDb()->where($condtion)->update($data);
	}

	/**
	 * 删除数据
	 * @param  mixed $condtion 
	 * @return bool         
	 */
	public static function destroy ($condtion = [])
	{
		$model = new static();
		if (is_string($condtion) && strpos($condtion,',') !== false) {
			$condtion = explode(',',$condtion);
		}
		if (is_array($condtion) && !empty($condtion) && is_number_array($condtion)) {
			return $model->whereIn($model->primaryKey,$condtion)->delete();
		}
		return $model->delete($condtion);
	}

	/**
	 * 删除数据
	 * @param  mixed $condtion 
	 * @return bool         
	 */
	public function delete ($condtion = [])
	{
		if (isset($this->data[$this->primaryKey]) 
			&& !empty($this->data[$this->primaryKey]) 
			&& !isset($condtion[$this->primaryKey])) {
			$condtion[$this->primaryKey] = $this->data[$this->primaryKey]; 
		}
		return $this->getDb()->delete($condtion);
	}

	/**
	 * 获取查询数据
	 * @param  mixed $condtion 
	 * @return Model           
	 */
	public static function get ($condtion = [])
	{
		$model = new static();
		return $model->find($condtion);
	}

	/**
	 * 获取查询数据
	 * @param  mixed $condtion 
	 * @return Model           
	 */
	public function find ($condtion = [])
	{
		$query = $this->getDb();
		$data = $query->find($condtion)->toArray();
		if (empty($data)) {
			return null;
		}
		return new static($data,!empty($data));
	}

	/**
	 * 获取查询数据
	 * @param  mixed $condtion 
	 * @return Model           
	 */
	public static function all ($condtion = [])
	{
		$model = new static();
		if (is_string($condtion) && strpos($condtion,',') !== false) {
			$condtion = explode(',',$condtion);
		}
		if (is_array($condtion) && !empty($condtion) && is_number_array($condtion)) {
			$model->getDb()->whereIn($model->primaryKey,$condtion);
			return $model->select();
		}
		return $model->select($condtion);
	}

	/**
	 * 查询数据
	 * @param  mixed $condtion 
	 * @return array     
	 */
	public function select ($condtion = [])
	{
		$query = $this->getDb()->where($condtion);
		$data = $query->select()->toArray();

		return Collection::make(
			array_map(function ($item) {
				$model = new static($item->toArray(),!$item->isEmpty());
				return $model;
			},$data)
		);
	}


	/**
	 * 设置字段
	 * @param array $fields 
	 * @param string $type   
	 */
	public function setField ($fields,$type = 'incrment')
	{
		$condtion = [];
		if (isset($this->data[$this->primaryKey]) 
			&& !empty($this->data[$this->primaryKey]) 
			&& !isset($condtion[$this->primaryKey])) {
			$condtion[$this->primaryKey] = $this->data[$this->primaryKey]; 
		}
		$result = $this->getDb()->where($condtion)->setField($fields,$type);
		if ($result) {
			array_walk($fields,function ($value,$field) use ($type) {
				if (isset($this->data[$field])) {
					if ($type == 'incrment') {
						$this->data[$field] += $value;
					} else {
						$this->data[$field] -= $value;
					}
				}
			});
		}
		return $result;
	}	

	/**
	 * 字段自增
	 * @param string  $field 
	 * @param integer $step  
	 * @return mixed
	 */
	public function incrment ($field,$step = 1)
	{
		return $this->setField([$field => $step],'incrment');
	}

	/**
	 * 字段自减
	 * @param string  $field 
	 * @param integer $step  
	 * @return mixed
	 */
	public function decrement ($field,$step = 1)
	{
		return $this->setField([$field => $step],'decrement');
	}

	/**
	 * build sql
	 * @return string
	 */
	public function buildSql ()
	{
		$query = $this->getDb();
		return $query->buildSql();
	}

	/**
	 * 修改是否更新数据
	 * @param  boolean $isUpdate 
	 * @return boolean           
	 */
	public function isUpdate ($isUpdate = true)
	{
		$this->isUpdate = $isUpdate;
		return $this;
	}

	/**
	 * 是否自动保存新增主键
	 * @param  boolean $isUpdate 
	 * @return boolean           
	 */
	public function isSavePrimaryKey ($isSavePrimaryKey = true)
	{
		$this->isSavePrimaryKey = $isSavePrimaryKey;
		return $this;
	}

	/**
	 * 获取主键key
	 * @return string
	 */
	public function getPrimaryKey ()
	{
		return $this->primaryKey;
	}

	/**
	 * 获取表名
	 * @return string
	 */
	public function getTableName ()
	{
		return $this->name;
	}

	/**
	 * 设置db对象
	 * @param Db $db 
	 * @return Model
	 */
	public function setDb ($db)
	{
		$this->db = $db;
		return $this;
	}

	/**
	 * 获取db对象
	 * @return Db
	 */
	public function getDb ($isSoftDelete = true)
	{
		if (!$this->db) {
			$this->db = $this->name ? Db::name($this->name) : Db::table($this->name);
			if (method_exists($this,'getSoftDeleteWhere') && $isSoftDelete) {
				$this->db->where($this->getSoftDeleteWhere());
			}
		}
		return $this->db;
	}

	/**
	 * 驼峰转下划线
	 * @param  string $camelCaps 
	 * @param  string $separator 
	 * @return string  
	 */
    protected function uncamelize($camelCaps,$separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }

    /**
     * 下划线转驼峰
     * @param  string $uncamelizedWords 
     * @param  string $separator         
     * @return string                  
     */
    protected function camelize($uncamelizedWords,$separator = '_')
    {
        $uncamelizedWords = $separator. str_replace($separator, " ", strtolower($uncamelizedWords));
        return ltrim(str_replace(" ", "", ucwords($uncamelizedWords)), $separator );
    }

    // ArrayAccess
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttr($offset,$value);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

	/**
	 * 无方法调用
	 * @param  string $method 
	 * @param  array $params 
	 * @return mixed
	 */
    public function __call($method,$params)
    {
    	$db = $this->getDb();
    	if (!method_exists($db, $method)) {
			throw new \RuntimeException(__CLASS__ . "->" . $method . ' is not exists!');
    	}
        $result = call_user_func_array([$db, $method], $params);
        return $result instanceof Query ? $this : $result;
    }

	/**
	 * 无静态方法调用
	 * @param  string $method 
	 * @param  array $params 
	 * @return mixed
	 */
    public static function __callStatic($method,$params)
    {
    	$model = new static();
    	$db = $model->getDb();
		if (!method_exists($db, $method)) {
			throw new \RuntimeException(__CLASS__ . "::" . $method . ' is not exists!');
    	}
        $result = call_user_func_array([$db, $method], $params);
        return $result instanceof Query ? $model : $result;
    }
}
