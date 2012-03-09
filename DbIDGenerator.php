<?php

/**
 * This is the model class for table "idgenerator".
 *
 * The followings are the available columns in table 'idgenerator':
 * @property string $nextid
 */
class DbIDGenerator
{
    const QUEUE_SIZE = 10;
    const END_OF_QUEUE = self::QUEUE_SIZE;
    private $offset;
    private $queue;

    static private $idgenerator;

    private function __construct()
    {
        $this->queue = array_fill(0, self::QUEUE_SIZE, 0);
        $this->offset = self::END_OF_QUEUE;
    }
    public function getNextID()
    {
        if ($this->offset == self::END_OF_QUEUE)
        {
            $this->fillQueueFromDb();
            $this->offset = 0;
        }
        
        return $this->queue[$this->offset++];
    }
    
    private function fillQueueFromDb()
    {
        
        $queueSize = self::QUEUE_SIZE;
        $sql = "update idgenerator set nextid=LAST_INSERT_ID(nextid+$queueSize)";

        Yii::app()->db->createCommand($sql)->execute();

        $dataReader = Yii::app()->db->createCommand("select LAST_INSERT_ID() as nextid")->query();
        $dataReader->bindColumn(1, $nextId);
        if($dataReader->read() !== false) {
            $i = self::END_OF_QUEUE;
            while ($i > 0)
            {
                $this->queue[--$i] = --$nextId;
            }

        }
    }

    public static function nextId() {
        if(DbIDGenerator::$idgenerator == null)
            DbIDGenerator::$idgenerator = new DbIDGenerator;
        return DbIDGenerator::$idgenerator->getNextID();
    }
}
