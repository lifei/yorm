<?php


/**
 * 
 * 实体类
 * - 读写分离
 * - ID生成器
 * - 工作单元
 * - 乐观离线锁
 * @author lifei
 *
 */
class CEntity extends CActiveRecord
{
	/**
	 * 读写分离
	 * @var boolean
	 */
	private $_isDbReading = TRUE;
	
	protected $cacheDuration = 3600;
	
	
	private $_isdirty = false;
	private $_dirtykeys = array();
	
	private $_disableCaching = false;
	private $_isReadingFromCache = false;
	
	private $_old = array();
	
	private $_isRemoved = false;
	
	/** 乐观离线锁的key字段 */
	protected $versionKey = false;
	
	/** 乐观离线锁的预存值 */
	protected $versionValue = 0;
	
	/**
	 * 
	 * @see CActiveRecord::getOldPrimaryKey()
	 */
	final public function getOldPrimaryKey()
	{
		$pk = $this->getOldPrimaryKey();
		
		if(!$this->versionKey) 
		{
			return $pk;
		}
		
		if(is_array($pk)) {
			if(isset($pk[$this->versionKey])) {
				return array($this->versionKey => $this->versionValue) + $pk;
			}
			return $pk;
		}
		
		return array($this->primaryKey() => $pk, $this->versionKey => $this->versionValue);
	}
	
	/**
	 * Updates the row represented by this active record.
	 * All loaded attributes will be saved to the database.
	 * Note, validation is not performed in this method. You may call {@link validate} to perform the validation.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return boolean whether the update is successful
	 * @throws CException if the record is new
	 */
	public function update($attributes=null)
	{
		if(!$this->_isdirty) {
			return true;
		}
	
		$attributes = array_unique($this->_dirtykeys);
	
		if($this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
		if($this->beforeSave())
		{
			Yii::trace(get_class($this).'.update()','system.db.ar.CActiveRecord');
			if($this->_pk===null)
				$this->_pk=$this->getPrimaryKey();
			if(0 == $this->updateByPk($this->getOldPrimaryKey(),$this->getAttributes($attributes)))
			{
				return false;
			}
			$this->_pk=$this->getPrimaryKey();
			$this->afterSave();
			return true;
		}
		else
			return false;
	}
	
	/**
	 * @return boolean
	 */
	public function isDirty()
	{
		return $this->_isdirty && !empty($this->_dirtykeys);
	}
	
	protected function afterConstruct()
	{
		parent::afterConstruct();
		
        if($this->isNewRecord && $this->getPrimaryKey() == NULL) {
            $this->setPrimaryKey(DbIDGenerator::nextId());
        }
	}
	
	protected function beforeSave()
	{
		// 时间戳
	    if($this->isNewRecord) {
            $this->createtime=new CDbExpression('NOW()');
            $this->updatetime=new CDbExpression('NOW()');
        }
        else {
            $this->updatetime=new CDbExpression('NOW()');
        }
        
        // Dao更新状态
        $this->setDbWriting();
        
        // 乐观锁更新
        if($this->versionKey && isset($this->{$this->versionKey})) {
        	$this->versionValue = $this->{$this->versionKey};
        	$this->{$this->versionKey}++;
        }
		return parent::beforeSave();
	}
	
	protected function beforeDelete()
	{        
        $this->setDbWriting();
        return parent::beforeDelete();		
	}

	
	protected function beforeFind()
	{
		$this->enableCache();
		$this->setDbReading();
        parent::beforeFind();		
	}
	
	protected function afterSave()
	{
		parent::afterSave();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CActiveRecord::afterFind()
	 */
	protected function afterFind()
	{
		// 如果开启了cache
		// 实体不是从缓存读取的 或者 实体有修改
		if($this->isCacheEnable() && (!$this->_isReadingFromCache || $this->isDirty())) {
			$this->saveToCache();
		}
		parent::afterFind();
	}
	
	protected function saveToCache()
	{	
		$dbConnection = $this->getDbConnection();
		$cache = $this->getCacheInstance();
		$cacheKey = $this->getCackeKey();
		
		if($cache && isset($cache,$cacheKey))
		{
			$cache->set($cacheKey, $this->_attributes, $this->cacheDuration, null);
		}
	}

	/**
	 * @see CActiveRecord::refresh()
	 */
	public function refresh() {
		$this->resetReadingFromCache();
		$this->disableCache();
		$result = parent::refresh();
		$this->enableCache();
		return $result;
	}
	
	/**
	 * @see CActiveRecord::__set()
	 */
	public function __set($name, $value) {
	
		if($this->getAttribute($name) == $value)
		{
			return;
		}
		
		parent::__set($name, $value);		
		
		if(!$this->getIsNewRecord() && $this->hasAttribute($name))
		{
			$this->_dirtykeys[] = $name;
			$this->_isdirty = true;
		}
		
	}
	
	public function isDbReading()
	{
		return $this->_isDbReading;
	}
	
	/**
	 * 标记为删除
	 */
	public function remove()
	{
		$this->_isRemoved = true;
	}
	
	public function getIsRemoveRecord()
	{
		return $this->_isRemoved;
	}
	
	//////////////////////////////////////////////////////////////////////////////////////////////
	//                                          DAO方法                                         //
	//////////////////////////////////////////////////////////////////////////////////////////////
	protected function getCacheInstance()
	{
		
		$dbConnection = $this->getDbConnection();
	
		if($dbConnection->queryCachingCount>0
				&& $dbConnection->queryCachingDuration>0
				&& $dbConnection->queryCacheID!==false
				&& ($cache=Yii::app()->getComponent($dbConnection->queryCacheID))!==null)
		{
			return $cache;
		}
		$this->disableCache();
		return false;
	}
	
	/**
	 * @see CActiveRecord::instantiate()
	 */
	protected function instantiate($attributes) {
		$record = parent::instantiate($attributes);
		$record->_old = $attributes;
		
		if($this->_isReadingFromCache) {
			$record->setReadingFromCache();
		}
		return $record;		
	}

	/**
	 * 
	 * @param mixed $pk 主键
	 * @return string
	 */
	public function getCackeKey($pk = null) {
		if(null == $pk)
		{
			$pk = $this->getPrimaryKey();
		}		
		if($pk == null)
		{
			return false;
		}
		if(is_array($pk)) {
			return Yii::app()->name . get_class($this).join("-", $pk);
		} else  {
			return Yii::app()->name . get_class($this).$pk;
		}
	}	
	
	/**
	 * 
	 * @return mixed
	 */
	private function readFromCache($pk=null)
	{
		if($this->isCacheEnable())
		{
			return false;
		}
		
		$dbConnection = $this->getDbConnection();
		$cache = $this->getCacheInstance();
		$cacheKey = $this->getCackeKey($pk);
		
		if($cache && ($result=$cache->get($cacheKey))!==false)
		{
			Yii::trace('Query result found in cache','system.db.CDbCommand');
			return $result;
		}
		
		return false;
	}

	/**
	 * @see CActiveRecord::findByPk()
	 * @return CEntity
	 */
	public function findByPk($pk, $condition = '', $params = array())
	{
		$this->setDbReading();
		if(($result = $this->readFromCache($pk)) !== false) {
			$this->setReadingFromCache();
			$record = $this->populateRecord($result, true);
			$this->resetReadingFromCache();
			return $record;
		} else {
			$this->resetReadingFromCache();
			return parent::findByPk($pk);
		}
	}

	/**
	 * @see CActiveRecord::updateAll()
	 * @deprecated
	 */
	public function updateAll($attributes, $condition = '', $params = array()) {
		$this->setDbWriting();
		return parent::updateAll($attributes, $condition, $params);		
	}

	/**
	 * @see CActiveRecord::deleteAll()
	 * @deprecated
	 */
	public function deleteAll($condition = '', $params = array()) {
		$this->setDbWriting();
		return parent::deleteAll($condition, $params);			
	}

	protected function setDbReading()
	{
		$this->_isDbReading = true;
	}
	
	public function setDbWriting()
	{
		$this->_isDbReading = false;
	}
	
	private function disableCache()
	{
		$this->_disableCaching = true;
	}
	
	private function enableCache()
	{
		$this->_disableCaching = false;
	}
	
	private function setReadingFromCache()
	{
		$this->_isReadingFromCache = true;
	}
	
	private function resetReadingFromCache()
	{
		if($this->_isReadingFromCache)
			$this->_isReadingFromCache = false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	private function isCacheEnable()
	{
		return !$this->_disableCaching;
	}
}
