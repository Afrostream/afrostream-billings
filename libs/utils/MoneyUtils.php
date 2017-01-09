<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Swap\Builder;
use Cache\Adapter\Apcu\ApcuCachePool;

class MoneyUtils {
	
	private static $swapInstance = NULL;
	
	private static function getSwapInstance() {
		if(self::$swapInstance == NULL) {
			self::$swapInstance = (new Builder(['cache_ttl' => 3600]))
			->add('central_bank_of_czech_republic')
			->add('central_bank_of_republic_turkey')
			//->add('currency_layer', ['access_key' => 'secret', 'enterprise' => false])
			->add('european_central_bank')
			->add('fixer')
			->add('google')
			->add('national_bank_of_romania')
			//->add('open_exchange_rates', ['app_id' => 'secret', 'enterprise' => false])
			//->add('array', [['EUR/USD' => new ExchangeRate('1.5')]])
			->add('webservicex')
			//->add('xignite', ['token' => 'token'])
			->add('yahoo')
			//->add('russian_central_bank')
			->useCacheItemPool(new ApcuCachePool())
			->build();
		}
		return(self::$swapInstance);
	}
	
	//$currencyPair sample : XOF/EUR
	public static function getLatestRate($currencyPair) {
		$exchangeRate = self::getSwapInstance()->latest($currencyPair);
		return($exchangeRate->getValue());
	}
	
	public static function getAmountInCentsExclTax($amount_in_cents, $vatRate) {
		if($vatRate == NULL) {
			return($amount_in_cents);
		} else {
			return(intval(round($amount_in_cents / (1 + $vatRate / 100))));
		}
	}
	
	public static function getAmountExclTax($amount_in_cents, $vatRate) {
		if($vatRate == NULL) {
			return($amount_in_cents / 100);
		} else {
			return(($amount_in_cents / (1 + $vatRate / 100)) / 100);
		}
	}
	
	public static function getAmount($amount_in_cents) {
		return((float) ($amount_in_cents / 100));
	}
	
}

?>