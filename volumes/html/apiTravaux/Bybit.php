<?php

class Bybit extends AbstractAPI {


    public function __construct($coin,$stablecoin){
        $this->pair = strtoupper($coin.$stablecoin); 
        
    }
    public function getExName(){
        return get_class($this);
    }

    public function getPrice($req){

        if(!isset($req->result->price)){
            logDB($this->exchangeName.'.getPrice() impossible de récupérer le prix pour : '.$this->pair);
            return false;
        }
        else{
             $this->actualPrice = $req->result->price;
             return true;    
        }
        
    }


    public function priceQuery(){
        
        $url = 'https://api.bybit.com/spot/v3/public/quote/ticker/price?symbol='.$this->pair;
            
        $ch = curl_init($url);
        curl_setopt_array($ch,array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__,
                CURLOPT_CONNECTTIMEOUT => 5
        ));

        return $ch;
    }



}