<?php

class Bitfinex extends AbstractAPI {


    public function __construct($coin,$stablecoin){
        if($stablecoin == "USDT")
            $stablecoin = 'UST';
        $this->pair = strtoupper($coin.$stablecoin); 
        
    }
    
    public function getPrice($req){

        if(!isset($req[6])){
            logDB('Bitfinex.getPrice() impossible de récupérer le prix pour : '.$this->pair);
            return false;
        }
        else{
             $this->actualPrice = $req[6];
             return true;    
        }
        
    }


    public function priceQuery(){
        
        $url = 'https://api-pub.bitfinex.com/v2/ticker/t'.$this->pair;
            
        $ch = curl_init($url);
        curl_setopt_array($ch,array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__,
                CURLOPT_CONNECTTIMEOUT => 5
        ));

        return $ch;
    }

    public function getExName(){
        return get_class();
    }

}