<?

class TimestampBehavior extends CActiveRecordBehavior { 

    public function beforeSave($event) { 

        if($this->owner->isNewRecord) {
            $this->owner->createtime=new CDbExpression('NOW()');
        }
        else {
            $this->owner->updatetime=new CDbExpression('NOW()');
        }
    } 
}


// vim600:ts=4 st=4 foldmethod=marker foldmarker=<<<,>>>
// vim600:syn=php commentstring=//%s
