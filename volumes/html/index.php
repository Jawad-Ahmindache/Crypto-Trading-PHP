<?php

require __DIR__."/config.php";
require __DIR__."/functions.php";
require __DIR__."/Main.php";
require __DIR__."/AbstractAPI.php";

// On inclut toutes nos api
$listAPI = scandir(__DIR__."/api");



foreach($listAPI as $value){
    if(is_file((__DIR__."/api/".$value))){
        require __DIR__."/api/".$value;
    }
}



$listPair = [
    'XRP|USDT',
    'MATIC|USDT',
    'BNB|USDT',
    'SOL|USDT',
    'ALGO|USDT',
    'PHB|USDT',
    'TWT|USDT',
    'NEAR|USDT',
    'APT|USDT',
    'SAND|USDT',
    'AVAX|USDT',
    'ATOM|USDT',
    'GMT|USDT',
    'FTM|USDT',
    'EOS|USDT',
    'FLOW|USDT',
    'TRX|USDT',
    'XRP|USDC',
    'MATIC|USDC',
    'BNB|USDC',
    'SOL|USDC',
    'ALGO|USDC',
    'PHB|USDC',
    'TWT|USDC',
    'NEAR|USDC',
    'APT|USDC',
    'SAND|USDC',
    'AVAX|USDC',
    'ATOM|USDC',
    'GMT|USDC',
    'FTM|USDC',
    'EOS|USDC',
    'FLOW|USDC',
    'TRX|USDC'
];




while(true){
    foreach($listPair as $value){
        $pair = explode("|",$value);
        $launch = new Main($pair[0],$pair[1]);
    
        $step1 = $launch->getAllExchangePrice();
    
        if($step1){
            $launch->scanArbitrage();
        }
    }
}

