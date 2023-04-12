<?php
ini_set('max_execution_time',0);
define('__ARB_PERCENTAGE__',0.40);
define('__USER_AGENT__','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36 RuxitSynthetic/1.0 v5799692904 t632686385861839323 athfa3c3975 altpub cvcv=2 smf=0');
define('__SCANMODE_AMOUNT__', 1000);
define('__MIN_LIQUIDITY__', 1000);
require __DIR__."/listApiKey.php";

$tz = new DateTimeZone('Europe/Paris');
$dateTime = new DateTime();
$dateTime->setTimeZone($tz);

$GLOBALS['logJournal'] = array(
        'execId' => uniqid('ex_'),
        'date' => $dateTime->format('Y-m-d H:i:s'),
        'pair' => null,
        'exchangeHigh' => null,
        'exchangeLow' => null,
        'listExecution' => array() 
);


// CONSTANTE pour les status de withdraw et order
define('__STATUS_SUCCESS__', 'ok');
define('__STATUS_WAITING__', 'wait');
define('__STATUS_CANCEL__', 'cancel');



//ID du compte pour check combien de crypto on possède
define('__HUOBI_ACCOUNT_ID__', '');
define('__KUCOIN_ACCOUNT_ID__', '');
define('__KUCOIN_CLIENT_OID__', '');
//------------------------------------

//Réseau associé à chaque crypto
define('__CHAIN_NETWORK__',array(
        'Binance' => array(
                'USDT' => 'TRX',
                'XRP' => 'XRP',
                'MATIC' => 'MATIC',
                'BNB' => 'BSC',
                'SOL' => 'SOL',
                'ALGO' => 'ALGO',
                'PHB' => 'BSC',
                'TWT' => 'BSC',
                'NEAR' => 'NEAR',
                'APT' => 'APT',
                'SAND' => 'MATIC',
                'AVAX' => 'AVAX',
                'ATOM' => 'ATOM',
                'GMT' => 'SOL',
                'FTM' => 'FTM',
                'EOS' => 'EOS',
                'FLOW' => 'FLOW',
                'TRX' => 'TRX',
                'BUSD' => 'BSC',
                'USDC' => 'TRX'
        ),
        'Huobi' => array(
                'USDT' => 'trc20usdt',
                'XRP' => 'xrp',
                'MATIC' => 'matic1',
                'BNB' => 'bnb1',
                'SOL' => 'sol',
                'ALGO' => 'algo',
                'PHB' => 'phb', //en realité BEP20
                'TWT' => null,
                'NEAR' => 'near',
                'APT' => 'apt',
                'SAND' => null,
                'AVAX' => 'avax',
                'ATOM' => 'atom1',
                'GMT' => 'gmt', //en réalite SOL
                'FTM' => 'ftm',
                'EOS' => 'eos1',
                'FLOW' => 'flow',
                'TRX' => 'trx1',
        ),
        'Kucoin' => array(
                'USDT' => 'trx',
                'XRP' => 'xrp',
                'MATIC' => 'matic',
                'BNB' => 'bsc',
                'SOL' => 'sol',
                'ALGO' => 'algo',
                'PHB' => null,
                'TWT' => 'bsc',
                'NEAR' => 'near',
                'APT' => 'aptos',
                'SAND' => null,
                'AVAX' => 'avax',
                'ATOM' => 'atom',
                'GMT' => 'sol',
                'FTM' => 'ftm',
                'EOS' => 'eos',
                'FLOW' => 'flow',
                'TRX' => 'trx',
                'BTC' => 'btc',
                'USDC' => 'trx'
        ),

        'Okex' => array(
                'USDT' => 'USDT-TRC20',
                'XRP' => 'XRP-Ripple',
                'MATIC' => 'MATIC-Polygon',
                'BNB' => 'BNB-BSC',
                'SOL' => 'SOL-Solana',
                'ALGO' => 'ALGO-Algorand',
                'PHB' => null,
                'TWT' => null,
                'NEAR' => 'NEAR-NEAR',
                'APT' => 'APT-Aptos',
                'SAND' => 'MATIC-Polygon',
                'AVAX' => 'AVAX-Avalanche X-Chain',
                'ATOM' => 'ATOM-Cosmos',
                'GMT' => 'GMT-Solana',
                'FTM' => 'FTM-Fantom',
                'EOS' => 'EOS-EOS',
                'FLOW' => 'FLOW-FLOW',
                'TRX' => 'TRX-TRC20',
                'USDC' => 'USDC-TRC20'
        )
             
));



define('__BLACKLIST_COIN__',array(
        'Binance' => array(),
        'Huobi' => array('TWT','NEAR','SAND'),
        'Kucoin' => array('PHB','SAND'),
        'Okex' => array('PHB','TWT','SAND')
));

define('__ADDRESS_LIST__', array(
        'Binance' => [
                'USDT' => [
                        'address' => '',
                        'tag' => null
                ],
                'XRP' => [
                        'address' => '',
                        'tag' => '302430235'
                ],
                'MATIC' => [
                        'address' => '',
                        'tag' => null
                ],
                'BNB' => [
                        'address' => '',
                        'tag' => null
                ],
                'SOL' => [
                        'address' => '',
                        'tag' => null
                ],
                'ALGO' => [
                        'address' => '',
                        'tag' => null
                ],
                'PHB' => [
                        'address' => '',
                        'tag' => null
                ],
                'TWT' => [
                        'address' => '',
                        'tag' => null
                ],
                'NEAR' => [
                        'address' => '',
                        'tag' => null
                ],
                'APT' => [
                        'address' => '',
                        'tag' => null
                ],
                'SAND' => [
                        'address' => '',
                        'tag' => null
                ],
                'AVAX' => [
                        'address' => '',
                        'tag' => null
                ],
                'ATOM' => [
                        'address' => '',
                        'tag' => ''
                ],
                'GMT' => [
                        'address' => '',
                        'tag' => null
                ],
                'FTM' => [
                        'address' => '',
                        'tag' => null
                ],
                'EOS' => [
                        'address' => '',
                        'tag' => ''
                ],
                'FLOW' => [
                        'address' => '',
                        'tag' => null
                ],
                'TRX' => [
                        'address' => '',
                        'tag' => null
                ]
        ],
        'Huobi' => [
                'USDT' => [
                        'address' => '',
                        'tag' => null
                ],
                'XRP' => [
                        'address' => '',
                        'tag' => ''
                ],
                'MATIC' => [
                        'address' => '',
                        'tag' => null
                ],
                'BNB' => [
                        'address' => '',
                        'tag' => null
                ],
                'SOL' => [
                        'address' => '',
                        'tag' => null
                ],
                'ALGO' => [
                        'address' => '',
                        'tag' => null
                ],
                'PHB' => [
                        'address' => '',
                        'tag' => null
                ],
                'TWT' => [
                        'address' => null,
                        'tag' => null
                ],
                'NEAR' => [
                        'address' => null,
                        'tag' => null
                ],
                'APT' => [
                        'address' => '',
                        'tag' => null
                ],
                'SAND' => [
                        'address' => null,
                        'tag' => null
                ],
                'AVAX' => [
                        'address' => '',
                        'tag' => null
                ],
                'ATOM' => [
                        'address' => '',
                        'tag' => null
                ],
                'GMT' => [
                        'address' => '',
                        'tag' => null
                ],
                'FTM' => [
                        'address' => '',
                        'tag' => null
                ],
                'EOS' => [
                        'address' => '',
                        'tag' => ''
                ],
                'FLOW' => [
                        'address' => '',
                        'tag' => null
                ],
                'TRX' => [
                        'address' => '',
                        'tag' => null
                ]
        ],
        'Kucoin' => [
                'USDT' => [
                        'address' => '',
                        'tag' => null
                ],
                'XRP' => [
                        'address' => '',
                        'tag' => ''
                ],
                'MATIC' => [
                        'address' => '',
                        'tag' => null
                ],
                'BNB' => [
                        'address' => '',
                        'tag' => null
                ],
                'SOL' => [
                        'address' => '',
                        'tag' => null
                ],
                'ALGO' => [
                        'address' => '',
                        'tag' => null
                ],
                'PHB' => [
                        'address' => null,
                        'tag' => null
                ],
                'TWT' => [
                        'address' => '',
                        'tag' => null
                ],
                'NEAR' => [
                        'address' => '',
                        'tag' => null
                ],
                'APT' => [
                        'address' => '',
                        'tag' => null
                ],
                'SAND' => [
                        'address' => null,
                        'tag' => null
                ],
                'AVAX' => [
                        'address' => '',
                        'tag' => null
                ],
                'ATOM' => [
                        'address' => '',
                        'tag' => ''
                ],
                'GMT' => [
                        'address' => '',
                        'tag' => null
                ],
                'FTM' => [
                        'address' => '',
                        'tag' => null
                ],
                'EOS' => [
                        'address' => '',
                        'tag' => ''
                ],
                'FLOW' => [
                        'address' => '',
                        'tag' => null
                ],
                'TRX' => [
                        'address' => '',
                        'tag' => null
                ],
        ],
        'Okex' => [
                'USDT' => [
                        'address' => '',
                        'tag' => null
                ],
                'XRP' => [
                        'address' => '',
                        'tag' => ''
                ],
                'MATIC' => [
                        'address' => '',
                        'tag' => null
                ],
                'BNB' => [
                        'address' => '',
                        'tag' => null
                ],
                'SOL' => [
                        'address' => '',
                        'tag' => null
                ],
                'ALGO' => [
                        'address' => '',
                        'tag' => null
                ],
                'PHB' => [
                        'address' => null,
                        'tag' => null
                ],
                'TWT' => [
                        'address' => null,
                        'tag' => null
                ],
                'NEAR' => [
                        'address' => '',
                        'tag' => null
                ],
                'APT' => [
                        'address' => '',
                        'tag' => null
                ],
                'SAND' => [
                        'address' => null,
                        'tag' => null
                ],
                'AVAX' => [
                        'address' => '',
                        'tag' => null
                ],
                'ATOM' => [
                        'address' => '',
                        'tag' => null
                ],
                'GMT' => [
                        'address' => '',
                        'tag' => null
                ],
                'FTM' => [
                        'address' => '',
                        'tag' => null
                ],
                'EOS' => [
                        'address' => '',
                        'tag' => ''
                ],
                'FLOW' => [
                        'address' => '',
                        'tag' => null
                ],
                'TRX' => [
                        'address' => '',
                        'tag' => null
                ],
        ]
));