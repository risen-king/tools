<?php

namespace app\models;

use yii\db\ActiveRecord;

class Stock extends ActiveRecord
{
     public function rules()
    {
        return [
            [['symbol', 'code','name','ipo_date'], 'safe'],
        ];
    }
}