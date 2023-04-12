<?php

class Coinbase extends AbstractAPI{


    public function __construct($coin,$stablecoin){
        $this->apiKey = __COINBASE_APIKEY__;
        $this->pair = strtoupper($coin."-".$stablecoin); //on traduis la pair pour l'api binance
    }
    

    public function getPrice($req){
        if(!isset($req->price)){
            logDB('Coinbase.getPrice() impossible de récupérer le prix pour : '.$this->pair);
            return false;
        }
        else{
             $this->actualPrice = $req->price;
             return true;
        }
        
    }

    public function priceQuery(){
        
        $url = 'https://api.exchange.coinbase.com/products/'.$this->pair.'/ticker';
            
        $ch = curl_init($url);
        curl_setopt_array($ch,array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__
        ));

        return $ch;
    }

    public function getExName(){
        return get_class();
    }
//                logDB('Coinbase.isCurrencyAvailable() pair '.$this->pair.' introuvable');
     

}