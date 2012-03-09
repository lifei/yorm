<?

class IDGeneratorBehavior extends CActiveRecordBehavior { 

    public function afterConstruct($event) { 

        Yii::import('system.vendors.lifei.DbIDGenerator');

        if($this->owner->isNewRecord && $this->owner->getPrimaryKey() == NULL) {
            $this->owner->setPrimaryKey(DbIDGenerator::nextId());
        }
    } 
}


// vim600:ts=4 st=4 foldmethod=marker foldmarker=<<<,>>>
// vim600:syn=php commentstring=//%s
