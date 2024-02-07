# OpenExchangeRates
PHP client for fetching foreign exchange (fx rate) from openexchangerates.com

**Installation**
1) Add `qbil-software/openexchangerates` to `composer.json` file and run `composer update` 

    OR
    
    
1) Run composer require `qbil-software/openexchangerates` to install and add this package to `composer.json` file.       
        
2) `require_once 'vendor/autoload.php'` in your php file and create an instance of `Qbil\OpenExchangeRates\Exchange` class.e.g. `$exchange = new Qbil\OpenExchangeRates\Exchange($appId, $baseCurrency);` where `$appId` is your app id and `$baseCurrency` is base currency.

(See https://docs.openexchangerates.org/docs/authentication for more info about app_id and https://docs.openexchangerates.org/docs/set-base-currency for more info about base currency). Default base currency is USD. If you dont wish to change base currency, don't pass second parameter.
    
3) You are ready to use the client now.



**Methods**

The client provides five public methods `latest`, `historical`, `between`, `convert` and `currencies`.

All methods except `currencies` (which accepts [different arguments](https://docs.openexchangerates.org/reference/currencies-json)) can accept an associative array as argument with following keys

1) `symbols` or `currencies` -  Get foreign exchange rates of only give currencies (in comma separated format). 
<br /> <br /> For example <br /> `$exchange->latest(['symbols' => 'USD,EUR,GBP'])` will return an array of latest foreign exchange rates of currencies USD, EUR and GBP
2) `base` - Even if you passed base currency as second argument to constructor, you can override it in each request adding `base` key to arguments array
<br /> <br /> For example <br /> `$exchange->latest(['base' => 'GBP'])`  will return an array of latest foreign exchange rates with respect to base currency GBP

**Methods explained** 
1) `latest` - This method fetches latest foreign exchange rates. e.g. `$exchange->latest()` will return an array of latest exchange rates.

2) `historical` - This method fetches foreign exchange rates of a particular date. It has a required argument key `date` (yyyy-mm-dd format), i.e. the date of which foreign exchange rates you want to fetch .e.g. `$exchange->historical(['date' => '2017-10-01'])` will return an array of exchange rates of date 2017-10-01.

3) `between` - This method fetches foreign exchange rates between particular dates specified by arguments key `start` and `end` (both in yyyy-mm-dd format) .e.g. `$exchange->between(['start' => '2017-10-01', 'end' => '2017-11-05'])` will return an array of exchange rates of between 2017-10-01 and 2017-11-05.

4) `convert` - This method is used to convert any money value from one currency to another at the latest foreign exchange rates. It has three required argument keys `amount`, `from` and `to`. `from` and `to` are currency codes (3 letters) and `amount` is the amount you want to convert. e.g. `$exchange->between(['amount' => '15678800', 'from' => 'USD', 'to' => 'EUR'])` will return equivalent amount in EUR (as string)

5) `currencies` - This method returns array of all supported currencies with symbol as their key and currency as their value.

Note: Some of the above methods are only available in enterprise or ultimate edition. Please visit https://openexchangerates.com for more info

