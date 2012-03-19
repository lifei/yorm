<?php

/**
 * 
 * Enter description here ...
 * @author lifei
 *
 */


class CUController extends CController
{
	protected $readOnly = false;
	
	protected function afterAction($action)
	{
		parent::afterAction($action);
		
		if(!$this->readOnly)
			Yii::app()->unitOfWork->commit();
	}	
}