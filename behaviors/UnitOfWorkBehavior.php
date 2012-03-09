<?

class UnitOfWorkBehavior extends CActiveRecordBehavior { 

    public function afterConstruct($event) {
    	Yii::app()->unitOfWork->register($this->owner);
    }
}


// vim600:ts=4 st=4 foldmethod=marker foldmarker=<<<,>>>
// vim600:syn=php commentstring=//%s
