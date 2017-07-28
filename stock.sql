CREATE TABLE  IF NOT EXISTS  `stock` (
    
    `id`    INT         NOT NULL  AUTO_INCREMENT,
    `symbol`  VARCHAR(12)         NOT NULL,
    `name`  VARCHAR(50) NOT NULL,
    `ipo_date` date,
     
    PRIMARY KEY (`id`)
   
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

 