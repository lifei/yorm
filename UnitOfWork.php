<?php


class UnitOfWork extends CComponent
{
	public $name;
	
	private $_items = array();
	private $_insertItems = array();
	private $_updateItems = array();
	private $_deleteItems = array();
	
	private $_dbconns = array();
	
	/**
	 * 注册一个实体
	 * @param CEntity $entity
	 */
	public function register($entity)
	{
		$cacheKey = $entity->getCackeKey();		
		$item[$cacheKey] = $entity;
		$entity->getDbConnection()->beginTransaction();
	}
	
	public function commit()
	{
		foreach($this->_items as $key => $entity)
		{
			if($entity->getIsRemoveRecord())
			{
					$this->_deleteItems[] = $entity;
			} 
			else if($entity->isDirty())
			{
				if($entity->getIsNewRecord()) {
					$this->_insertItems[] = $entity;
				} else {
					$this->_updateItems[] = $entity;
				}
			}
			
			$entity->setDbWrite();
			$db = $entity->getDbConnection();
			$this->dbconns["{$db->connectionString}-{$db->username}"] = $db;
		}
			
		foreach($this->_dbconns as $conn)
		{
			$entity->getDbConnection()->beginTransaction();
		}
		
		try {
			
			foreach ($this->_updateItems as $key => $entity) {
				if($entity->update())
					throw new CDbException('并发冲突');
			}
			
			foreach ($this->_insertItems as $key => $entity) {
				$entity->save(false);
			}
			
			foreach ($this->_deleteItems as $key => $entity) {
				$entity->delete();
			}
			
			foreach($this->_dbconns as $conn)
			{
				$entity->getDbConnection()->commit();
			}	
			
		} catch (Exception $e) {
			foreach($this->_dbconns as $conn)
			{
				$entity->getDbConnection()->rollback();
			}
			
			throw $e;
		}
		
	}
	
	public function rest()
	{		
		$this->updateItems = array();
		$this->insertItems = array();
		$this->deleteItems = array();
		$this->items = array();
		$this->dbconns = array();		
	}
}
