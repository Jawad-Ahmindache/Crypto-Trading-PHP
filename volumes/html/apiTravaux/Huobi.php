<?php

class Huobi extends AbstractAPI {

    private $accountId = __HUOBI_ACCOUNT_ID__;
    public function __construct($coin,$stablecoin){
        parent::__construct();
        $this->pair = strtolower($coin.$stablecoin); 
        $this->stableCoin = strtolower($stablecoin);
        $this->coin = strtolower($coin);

    }
    
 
    public function orderBookQuery(){
        return $this->apiQuery('GET','https://api.huobi.pro/market/depth',array('symbol' => $this->pair,'type' => "step0"),[],false);
    }

    
     /************************************
            ***FONCTION D'EXECUTION ****
     ************************************
     ***********************************/

    private function create_sig($method,$chemin,$param) {
		$sign_param_1 = $method."\n".'api.huobi.pro'."\n".$chemin."\n".implode('&', $param);
		$signature = hash_hmac('sha256', $sign_param_1, __LISTKEY__['Huobi']['secret'], true);
		return base64_encode($signature);
	}

    private function create_sign_url($method,$chemin,$append_param = []) {
		$param = [
			'AccessKeyId' => __LISTKEY__['Huobi']['api'],
			'SignatureMethod' => 'HmacSHA256',
			'SignatureVersion' => 2,
			'Timestamp' => date('Y-m-d\TH:i:s', time())
		];
		if ($append_param) {
			foreach($append_param as $k=>$ap) {
				$param[$k] = $ap; 
			}
		}
		return 'https://api.huobi.pro'.$chemin.'?'.$this->bind_param($method,$chemin,$param);
	}
    private function bind_param($method,$chemin,$param) {
		$u = [];
		$sort_rank = [];
		foreach($param as $k=>$v) {
			$u[] = $k."=".urlencode($v);
			$sort_rank[] = ord($k);
		}
		asort($u);
		$u[] = "Signature=".urlencode($this->create_sig($method,$chemin,$u));
		return implode('&', $u);
	}


    //Fonction qui build la requête api
    public function apiQuery(string $type,string $url,array $data,array $header,bool $enableSigned){
        $header[] = 'Content-Type: application/json';
        $url = explode('https://api.huobi.pro', $url);
        $url = $this->create_sign_url($type, $url[1],$data);


        $ch = curl_init($url);
        
        curl_setopt_array($ch,array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CUSTOMREQUEST => $type,
                CURLOPT_POSTFIELDS => $type != 'GET' ? json_encode($data) : null,
                CURLOPT_HTTPHEADER => $header,
             //   CURLINFO_HEADER_OUT => true
        ));
        return $ch;

    }


    public function takePriceInfo($orderList = null){
        
        if ($this->scanMode == true && $orderList == null) {
            echo ("Impossible de lancer la fonction " . $this->exchangeName . ".takePriceInfo en mode scan si orderList est null".PHP_EOL);
            return false;
        }
        $book = ($this->scanMode == false) ? $this->getOrderBook() : $this->getOrderBook($orderList);
    
        if (!isset($book[0])) 
            return false;
        else{
            $currencyBalance = ($this->scanMode == true) ? __SCANMODE_AMOUNT__ : $this->getCoinAmount();
            $priceCoin = 0;
            $priceStableCoin = 0;
            $liquidity = 0;
            $nbOrder = 0;
            for ($i = 0; $i < count($book); $i++){
                $priceCoin += $book[$i][1];
                $priceStableCoin += $book[$i][0];
                $liquidity += ($book[$i][1] * $book[$i][0]);
                $nbOrder++;
                if ($liquidity >= $currencyBalance)
                    break;
            }
            if ($liquidity < $currencyBalance) {
                return false;
            }else{
                $this->priceCoin = $priceCoin/$nbOrder;
                $this->priceStableCoin = $priceStableCoin/$nbOrder;
                $this->liquidity = $liquidity;

                return true;
            }


        }
            
}



public function getOrderBook($orderList = null){


    if ($this->scanMode == false && $orderList != null)
        die($this->exchangeName.".getOrderBook() Impossible d'envoyer un orderList si le scanMode n'est pas activé");

    $query = $this->orderBookQuery();
    
    $type = $this->side == 'sell' ? 'asks' : 'bids';
    $result = ($this->scanMode == true) ? $orderList : json_decode_print(curl_exec($query));

    if (isset($result->tick->{$type}))
        return $result->tick->{$type};
        
    else{
        pushLogJn($this->exchangeName,'getOrderBook', true, 'Impossible de récupérer l\'OrderBook',
                                                    array('pair' => $this->pair,'side' => $this->side),json_encode($result));
        return false;
    }
}


    public function getExName(){
        return get_class($this);
    }

    
    public function getCoinAmount(){
        $coin = ($this->side == "buy") ? $this->stableCoin : $this->coin;
        $query = $this->apiQuery('GET','https://api.huobi.pro/v1/account/accounts/'.$this->accountId.'/balance',['type' => 'spot','currency' => $coin],[],true);
        $result = json_decode_print(curl_exec($query));
        
      foreach($result->data->list as $value){
            if($value->type == "trade" && $value->currency == strtolower($coin)){
                return (double)$value->balance;
            }
        }
        pushLogJn($this->exchangeName, 'getCoinAmount', true, 'Impossible de récupérer le montant', 
                     array('side' => $this->side, "coin" => $coin, "pair" => $this->pair),json_encode($result));
        return false;
  
    }

    

    public function convertCoin(){
       

        $quantity = $this->getCoinAmount();
        $quantity = $this->side == "buy" ? ($quantity/$this->priceStableCoin) : $quantity;

        $side = $this->side == "buy" ? "buy-market" : "sell-market";
        $quantity--;

        do {
            if(isset($result->{'err-msg'})){
                preg_match('/scale: `([0-9])`/',$result->{'err-msg'},$matches);
                $precision = $matches[1];
              
            }
            $query = $this->apiQuery('POST', 'https://api.huobi.pro/v1/order/orders/place', ['account-id' => $this->accountId, 'type' => $side, 'symbol' => $this->pair, 'amount' => round($quantity, !isset($precision) ? 8 : $precision)], [], true);
            $result = json_decode_print(curl_exec($query));
        } while (str_contains($result->{'err-msg'}, 'order amount precision error'));

  
        if(isset($result->data)){
            if (strlen($result->data) > 1 && $result->data !== NULL) {
                $this->orderId = $result->data;
                return $result->data;
            }

            pushLogJn($this->exchangeName, 'convertCoin', true, 'La conversion n\'a pas pu se lancer', 
                      array('side' => $this->side, "pair" => $this->pair));           
            return false;
        }
        else{
            pushLogJn($this->exchangeName, 'convertCoin', true, 'Impossible de convertir', 
                      array('side' => $this->side, "pair" => $this->pair),json_encode($result));           
            return false;
        }
  
        
    }

    
    
    // Envoyer le coin vers un autre exchange
    public function withdrawCoin($coin,$amount,$address,$tag = null){
        $coin = strtolower($coin);
        $param = array();
        $param['currency'] = $coin;
        $param['amount'] = $amount;
        $param['address'] = $address;
        $param['chain'] = $this->getNetwork($coin);
      
        if ($tag != null)
            $param['addr-tag'] = $tag;
        $query = $this->apiQuery('POST','https://api.huobi.pro/v1/dw/withdraw/api/create',$param,[],true);
        $result = json_decode_print(curl_exec($query));
        arrayPrint($result);
        if (isset($result->id)) {
            $this->withdrawId = $result->id;
            return $result->id;
        }else{
            pushLogJn($this->exchangeName, 'widthdrawCoin', true,  'Envoi impossible', 
                     array('side' => $this->side, "pair" => $this->pair,'coin' => $coin,'network' => $this->getNetwork($coin),'address' => $address,'tag' => $tag),
                     json_encode($result)
            );
            return false;
        }
    }

    public function checkWithdrawStatus(){
        $query = $this->apiQuery('GET', 'https://api.huobi.pro/v1/dw/withdraw-virtual/692671163991102', ['type' => 'withdraw'],[],true);
        $result = json_decode_print(curl_exec($query));
        arrayPrint($result);
        $withdraw = null;
        die();
        if(!isset($result->id)){
            pushLogJn($this->exchangeName, 'checkWithdrawStatus', true,  'Impossible de check le withdraw', 
                array('side' => $this->side, "pair" => $this->pair,'withdrawId' => $this->withdrawId ),
                json_encode($result));
            return false;
        }
        foreach($result->data as $value){
            if($value->id == $this->withdrawId){
                $withdraw = $value;
                break;
            }
                
        }
        if($withdraw == null){
            pushLogJn($this->exchangeName, 'checkWithdrawStatus', true,  'Envoi impossible', 
            array('side' => $this->side, "pair" => $this->pair,'withdrawId' => $this->withdrawId ));
            return false;
        }else{
            if ($withdraw->status == 0 || $withdraw->status == 2 || $withdraw->status == 4)
                return __STATUS_WAITING__;
            else if ($withdraw->status == 6)
                return __STATUS_SUCCESS__;
            else{
                pushLogJn($this->exchangeName, 'checkWithdrawStatus', true,  'L\'envoie a été annulé', 
                array('side' => $this->side, "pair" => $this->pair,'withdrawId' => $this->withdrawId,'withdrawStatus' => $withdraw->status ));
                return false;
            }
                
        }
    }

     //Check if a conversion coin-stablecoin or stablecoin-coin is finished (aussi appelé order par exemple sur binance)
     public function checkConvertStatus(){
        $query = $this->apiQuery("GET", "https://api.huobi.pro/v1/order/orders/692650142082083", [], [], true);
        $result = json_decode_print(curl_exec($query));

        if(!isset($result->data->id)){
            pushLogJn($this->exchangeName,"checkConvertStatus",true,"Impossible d'obtenir le resultat de cet order",
                    ['pair' => $this->pair, 'orderId' => $this->orderId],
                    $result
             );
        }else{
            if ($result->data->state == "filled")
                return __STATUS_SUCCESS__;
            else if ($result->data->state == "created" or $result->data->state == "submitted" or $result->data->state == "partial-filled")
                return __STATUS_WAITING__;
            else {
                pushLogJn($this->exchangeName,"checkConvertStatus",true,"L'ordre a été annulé",
                          ['pair' => $this->pair, 'orderId' => $this->orderId, "orderStatus" => $result->data->state]);
                return false;
                
            }
        }
     }





}
