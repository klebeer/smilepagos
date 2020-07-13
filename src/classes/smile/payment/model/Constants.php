<?php


namespace smile\payment\model;


class Constants
{
    const LOGGER_FOLDER = _PS_ROOT_DIR_ . '/smileLogs/';
    const LOGGER_FILE = Constants::LOGGER_FOLDER . 'smile.log';
    const TRANSACTION_APPROVED_TEST = array('000.100.112', '000.100.110');
    const TRANSACTION_APPROVED_PROD = '000.000.000';
}