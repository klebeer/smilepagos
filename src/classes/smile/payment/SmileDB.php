<?php


namespace smile\payment\smile\payment;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class SmileDB
{

    public function createDatabase()
    {
        $this->getLogger()->info("Creating Smile Pagos table");
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'smilepagos (
            `id` INTEGER(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `cart_id` INTEGER(11) DEFAULT NULL,
            `customer_id` INTEGER(11) DEFAULT NULL,
            `total` FLOAT(11) DEFAULT NULL,
            `message` VARCHAR (100) ,
            `status` VARCHAR (100),
            `authorization` VARCHAR (6) DEFAULT NULL,
            `transaction_id` VARCHAR (32) DEFAULT NULL,
            `batch` VARCHAR (6) DEFAULT NULL,
            `reference_number` VARCHAR (6) DEFAULT NULL,
            `response_code` VARCHAR (2) DEFAULT NULL,
            `acquirer_code` VARCHAR (4) DEFAULT NULL,
            `customer_params` VARCHAR (85) DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL)
            ENGINE = ' . _MYSQL_ENGINE_ . ' '
        );
    }

    /**
     * @return Logger
     */
    private function getLogger(): Logger
    {
        $logger = new Logger('SmileDB');
        $logger->pushHandler(new StreamHandler(Constants::LOGGER_FILE, Logger::DEBUG));
        return $logger;
    }


}
