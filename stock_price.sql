CREATE TABLE IF NOT EXISTS `stock_price`(
   `id` INT UNSIGNED AUTO_INCREMENT,
   `date` DATE,
   `symbol` INT UNSIGNED NOT NULL,# 股票代码
   `name` VARCHAR(50),# 股票名称
   
   `close`  DECIMAL(6,2),# 收盘价
   `high`   DECIMAL(6,2),#最高价
   `low`    DECIMAL(6,2),#最低价
   `open`   DECIMAL(6,2),#开盘价
   `adj_close`    DECIMAL(6,2), #前收盘

   `change`       DECIMAL(6,2), #涨跌额
   `changeg_rate` DECIMAL(8,4), #涨跌幅
   
   `exchange`  DECIMAL(4,4), # 换手率
   
   `vol` BIGINT UNSIGNED,  # 成交量
   `aomount` BIGINT UNSIGNED,  #成交额
   `gmw` BIGINT UNSIGNED,   # 总市值
   `emv` BIGINT UNSIGNED,   # 流通市值


   PRIMARY KEY (`id`)
   
)ENGINE=InnoDB DEFAULT CHARSET=utf8;
