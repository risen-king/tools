<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\httpclient\Client;
use yii\validators\DateValidator;
use yii\db\Query;
use QL\QueryList;

 
use app\models\Stock;
use app\models\StockPrice;

class StockController extends Controller
{
     
    public $baseUrl = 'http://quotes.money.163.com/';
    
    public $fakeData =   [
                [
                    'symbol'=>600006,
                    'name'=>'东风汽车',
                    'code'=>'sh600006',
                    'ipo_date'=>'1999-07-21'
                ]
            ];

    public $batch=true;


    public function options($actionID)
    {
        return array_merge(parent::options($actionID),[
            'batch'
        ]);
 
    }
    
    public function optionAliases()
    {
        //return ['b' => 'batch'];

        return array_merge(parent::optionAliases(),[
            'b' => 'batch'
        ]);
    }

     
    /*
    * 获取股票详细信息
    */
    private function getInfo($code)
    {
        
        //echo "正在采集数据，请耐心等待...".PHP_EOL;

        //待采集的目标页面
        $infoUrl = $this->baseUrl . "f10/gszl_{$code}.html";
        
        //采集规则
        $rules = array(
            'ipo_date' => ['tr:nth-child(2) td:nth-child(2)','text'],
      
        );
        

        //采集
        $data = QueryList::Query($infoUrl, $rules, '.col_r_01' )
                    ->getData(function ($item) {
                        return $item;
                    });

        
        if (empty($data)) {
            throw new \Exception($code . ' 获取公司信息失败');
        }

      
        return $data;
    }


    /*
    *   获取 IPO 日期
    */
    private function getIpoDate($code)
    {
        

        
        
        
        $data =$this->getInfo($code);

        if (!isset($data[0])  ||  !isset($data[0]['ipo_date'])) {
            throw new \Exception($code . ' 获取公司上市日期失败');
        }



        //验证日期
        $_ipo_date = trim($data[0]['ipo_date']);
        $error = '';
        $dateValidator = new DateValidator([
            'format'=>'php:Y-m-d'
        ]);

        $ipo_date = $dateValidator->validate($_ipo_date, $error) ? $_ipo_date : null;

        
        return $ipo_date;
    }

    


    /**
     * 采集股票代码
     *
     * @return string
     */
    private function getStockList($params)
    {

        /********************** 采集数据 ************************************/

        echo "正在采集数据，请耐心等待...".PHP_EOL;


        // 可用 fields 列表
        // NO,SYMBOL,NAME,PRICE,PERCENT,UPDOWN,FIVE_MINUTE,OPEN,YESTCLOSE,HIGH,LOW,VOLUME,TURNOVER,HS,LB,WB,ZF,PE,
        // MCAP,TCAP,MFSUM,MFRATIO.MFRATIO2,MFRATIO.MFRATIO10,SNAME,CODE,ANNOUNMT,UVSNEWS'
        $defaultParams = [
            'query'=> null,
            'fields'=>'SYMBOL,NAME',
            'count'=>5000,
            'page'=>0,
            'sort'=>'SYMBOL',
            'order'=>'asc',
            'type'=>'query',
            'host'=>'http://quotes.money.163.com/hs/service/diyrank.php',
        ];

       

        $params = array_merge($defaultParams, $params);

        

        if (!$params['query']) {
            
            print_r($params);
            
            throw new \Exception('缺少 query 参数');
        }
        
         
        $client = new Client(['baseUrl' => $this->baseUrl]);

        $response = $client->get('hs/service/diyrank.php', $params)->send();
        

        $response->content =  json_decode($response->content)  ;

        
        $list = array_filter($response->content->list);

      
        
        echo '股票代码采集完毕，下一步将获取股票信息，请耐心等待...' . PHP_EOL;
         
        
        /********************** 过滤数据 ************************************/
 
        $data = [];
        foreach ($list as $k => $item) {
            
            $prefix = isset($params['stock_change']) && $params['stock_change'] ? $params['stock_change'] : 'xx';


            $code = $prefix . $item->SYMBOL;
            
            $data[$code] = [
                'symbol'=>$item->SYMBOL,
                'code'=>$code,
                'name'=>$item->NAME
                
            ];
        }
        
        ksort($data);

        unset($list, $response);
 
 
 
        /**********************  数据入库 ************************************/
        foreach ($data as $item) 
        {
            $stock = Stock::findOne([
                'symbol'=>$item['symbol']
            ]);

            if( !$stock ){

                $stock = new Stock;

                $stock->attributes = $item;
 
                //$stock->symbol = $item['symbol'];
                //$stock->name = $item['name'];
                
                $stock->save();

                echo '插入数据： ' . '股票代码：'.$item['symbol'] . '  股票名称：'.$item['name'] . PHP_EOL;
 
            } 

            
            
        }

        echo '股票信息入库完毕，请查询日志。' . PHP_EOL;
        echo '*******************************************************' . PHP_EOL. PHP_EOL;


        return $data;

    }

     

    /**
     * 采集股票代码
     *
     * @return string
     */
    public function actionStockList($debug=false)
    {
        //沪深股市
        // $query = [
        //     'STYPE:EQA', //沪深A股
        //     'STYPE:EQB' , //沪深B股
        //     'STYPE:EQA;EXCHANGE:CNSESH',//沪市A股
        //     'STYPE:EQB;EXCHANGE:CNSESH',//沪市B股
        //     'STYPE:EQA;EXCHANGE:CNSESZ',//深市A股
        //     'STYPE:EQB;EXCHANGE:CNSESZ',//深市B股
        //     'STYPE:EQA;SME:true;NODEAL:false',//中小板
        //     'STYPE:EQA;GEM:true;NODEAL:false',//创业板
        //     '',//A+B股
        //     '',//A+H股
        //     'SHARE_STOCK:true',//沪股通
        //     'STYPE:EQA;PRICE_RNG:L',//高价股
        //     'STYPE:EQA;PRICE_RNG:M',//中价股
        //     'STYPE:EQA;PRICE_RNG:S',//低价股
        //     'SCSTC27_RNG:L',//大盘股
        //     'SCSTC27_RNG:M',//中盘股
        //     'SCSTC27_RNG:S',//小盘股
        //     'IPO_DATE:gt',//次新股
        //     'NODEAL:FXJS',//风险警示
        //     'NODEAL:TSZL'//退市整理
 
        // ];

        $query = [
            [
              'query'=>  'STYPE:EQA;EXCHANGE:CNSESH',//沪市A股
              'stock_change'=>'sh'
            ],

            [
              'query'=>  'STYPE:EQB;EXCHANGE:CNSESH',//沪市B股
              'stock_change'=>'sh'
            ],

            [
              'query'=>  'STYPE:EQA;EXCHANGE:CNSESZ',//深市A股
              'stock_change'=>'sz'
            ],
            [
              'query'=>  'STYPE:EQB;EXCHANGE:CNSESZ',//深市B股
              'stock_change'=>'sz'
            ]
        ];

        if($debug){
            $query = array_map(function($item){
                $item['count'] = 10;
                return $item;
            },$query);

        }


        
        $all_data = [];
        foreach ($query as $params) {
             
            $data = $this->getStockList($params);

            $all_data = array_merge($all_data, $data);
        }

        
        

        if($debug){

            // $new = [];
            // foreach($all_data as $k=>$v){
            //     $_key = substr($k,0,4);
            //     $new[$_key] = substr($k,1);

            // }
            // ksort($new);

            print_r(count($all_data) . PHP_EOL);
            print_r($all_data);

        }
        
     
    }

    
    /**  
    * 修复上市日期   
    * @param mixed
    * @return mixed
    */
    public function actionFixIpoDate($debug=false){

        if(!$debug){
            $data = Stock::find()
                ->where([
                            'ipo_date' => null
                        ])
                ->orderBy('symbol ASC')->all();
    
        }else{
            $data = $this->fakeData;
            
        }

      
        foreach ($data as $k => $item)
        {
            try {

                if( !isset( $item['ipo_date'] ) || empty( $item['ipo_date'] ) )
                {
                    $ipo_date = $this->getIpoDate( $item['symbol'] );

                  
                    if( \is_object($item) ){
                       
                        $item->ipo_date = $ipo_date;
                        $item->save();

                    }

                    echo '股票代码：'.$item['symbol'] . '  股票名称：'.$item['name'] . '  上市日期：'.$item['ipo_date'] . PHP_EOL;
                }
  

            } catch (\Exception $e) {

                
                Yii::warning( $e->getMessage() );

            }finally{
                
                
            }

        }
 
        echo 'IPO 日期修复完毕...' . PHP_EOL;
    }
 

    
    /**
     * 股票详细信息
     * @param mixed $code
     * @return mixed
     */
    public function actionInfo($symbol,$debug=false)
    {

        $data = $this->getInfo($symbol);
 
        print_r($data);
    }
    

    
    /**
     * 采集股票历史价格
     *
     * @return string
     */
    public function actionPrice($fast=true,$debug=false)
    {
  
         

        $stockList = Stock::find()
                            ->asArray()
                            ->where([
                                    'ipo_date' => null
                                ])
                            ->all();

       
        $query = new Query();
 
        foreach ($stockList as $stock) 
        {

            $priceList = $this->getPrice($stock);
 
            
            $keys = array_keys( current($priceList) );
            $countPerStock = count($priceList);
            
            $start_time = time();

           
            if( $this->batch === true )
            {
          
                $chunkSize = 100;
                $chunkList = array_chunk($priceList,$chunkSize,true);
                $chunkCount = count($chunkList);
                $chunkPos = 0;
               
                foreach($chunkList as $chuntItem){
                   
                    ++$chunkPos;
                    $currentEle = current($chuntItem);
                    
                    Yii::$app->db->createCommand()
                            ->batchInsert('stock_price',$keys,array_values($chuntItem) )
                            ->execute();

                    echo \Yii::t('app', '插入第( {chunkPos}/{chunktCnt}) )组，每组 {chunkSize}条  股票代码：{symbol}  股票名称：{name}' .PHP_EOL, [
                            'chunkPos' => $chunkPos,
                            'chunktCnt'=> $chunkCount,
                            'chunkSize'=>$chunkSize,
                            'symbol'=>$currentEle['symbol'],
                            'name'=> $currentEle['name'],
                            'date'=>$currentEle['date']

                    ]);

                   

                }


                echo \Yii::t('app', "插入数据 {count} 组，每组 {chunkSize} 条，用时 {spent} s\n" ,[
                    'count'=>$countPerStock,
                    'chunkSize'=>$chunkSize,
                    'spent'=> time() - $start_time

                ]);

                
 

            }
            else
            {
                
                $insert_num = 0;//插入条数
                
                foreach($priceList as $k => $item)
                {
  
                    $stockPrice = null;
                  

                    // $stockPrice = StockPrice::find()->limit(1)
                    //                 ->where([
                    //                     'symbol'=>$item['symbol'],
                    //                     'date'=>$item['date']
                    //                 ])
                    //                 ->one();

                    
                    if( !$stockPrice )
                    {

                        $stockPrice = new StockPrice;

                        $stockPrice->attributes = $item;

                        $stockPrice->save();


                        echo \Yii::t('app', '插入数据({position}/{count})： 股票代码：{symbol}  股票名称：{name} 日期： {date}' .PHP_EOL, [
                            'position' => $k+1,
                            'count'=>$countPerStock,
                            'symbol'=>$item['symbol'],
                            'name'=> $item['name'],
                            'date'=>$item['date']

                        ]);

                        ++$insert_num;

                    }
                    else
                    {
                        echo \Yii::t('app', '已存在数据({position}/{count})： 股票代码：{symbol}  股票名称：{name} 日期： {date}' .PHP_EOL, [
                            'position' => $k+1,
                            'count'=>$countPerStock,
                            'symbol'=>$item['symbol'],
                            'name'=> $item['name'],
                            'date'=>$item['date']

                        ]);

                    }


                    
    
                }
    

                echo \Yii::t('app', "插入数据 {$insert_num} 条，用时 {spent} s\n" ,[
                    'count'=>$countPerStock,
                    'spent'=> time() - $start_time

                ]);


            }

            
            

           

        }
    }

    private function getPrice($item)
    {
    
        $pre = substr( trim( $item['code'] ),0,2 );

        if( $pre === 'sh' ){
            $prefix = '0';
        }elseif ( $pre === 'sz' ){
            $prefix = '1';
        }else{
            throw new \Exception('未知的股票类别 '.$item['code']);
        }


        $_time_str = $item['ipo_date'] ? $item['ipo_date'] : "-10 year";
        
        $start = date( "Ymd", strtotime( $_time_str ) );
        $end   = date( "Ymd", time() ) ;
         
        
        $params = [
            'code'=> $prefix.$item['symbol'],
            'start'=>$start,
            'end'=>  $end,
            'fields'=>'TCLOSE;HIGH;LOW;TOPEN;LCLOSE;CHG;PCHG;TURNOVER;VOTURNOVER;VATURNOVER;TCAP;MCAP'
        ];
        
 
        
        //待采集的目标页面
        $client = new Client(['baseUrl' => $this->baseUrl]);
        
        $response = $client->get('service/chddata.html', $params)->send();
       

        if ($response->isOK) {

            $response->content = $this->convert( $response->content );
            
            $_data = array_filter( explode("\n", $response->content) );

        } else {

            $_data = null ;
        }

        unset($client);

        unset($_data[0]);

        
        $keys = ['date','symbol','name','close','high','low','open','adj_close','change','changeg_rate','exchange','vol','aomount','gmw','emv'];
        $data = [];
        foreach($_data as $k=>$_item){

                $item = explode(',',$_item);

                $item[1] = substr($item[1],1);

                $item = array_combine($keys,$item);
                
                $data[$k] = $item;

                unset($_data[$k]);
 
        }

     
        return $data;
    }

    /**

    *  修复 PHP Notice 'yii\base\ErrorException' with message 'iconv(): Detected an illegal character in input string' 错误

    * @var mixed

    */
    private function convert( $str,$from='GB2312',$to='UTF-8' ){
        
        try{
            $result = iconv('GB2312', $to.'//IGNORE', $str );
        }catch(Exception $e){

            Yii::trace($e->getMessage(), __METHOD__);

            throw $e;
        }
        

    }
}
