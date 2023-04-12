<?php

class Binance extends AbstractAPI {


    // Spécifique à l'api de binance
    public $allCoinInfo = null;
    

    public function __construct($coin,$stablecoin){
        parent::__construct();

        $this->coin = strtoupper($coin);
        $this->stableCoin = strtoupper($stablecoin);
        $this->pair = $this->coin.$this->stableCoin;

        if ($this->stableCoin == "USDC")
            $this->stableCoin = "BUSD";
    }
    

     /***********************************
     ******** FONCTION DE SCAN **********
     ************************************
     ************************************/


   
     public function orderBookQuery(){
            return $this->apiQuery('GET','https://api.binance.com/api/v3/depth',array('symbol' => $this->pair),[],false);
     }




     /************************************
            ***FONCTION D'EXECUTION ****
     ************************************
     ***********************************/

    //Fonction qui build la requête api
    public function apiQuery(string $type,string $url,array $data,array $header,bool $enableSigned){
        
        if($enableSigned){
            $header[] = 'X-MBX-APIKEY: '.__LISTKEY__['Binance']['api'];
            $header[] = 'Accept-Language:	fr-FR,fr;q=0.9,en;q=0.8,en-US;q=0.7';
            $timestamp = time().'000';
            $stringData = 'timestamp='.$timestamp.'&recvWindow=30000';
            
            foreach($data as $key => $value){
                $stringData .= '&'.$key.'='.$value;
            }
    
            $signature = hash_hmac('sha256',$stringData,__LISTKEY__['Binance']['secret']);
            
           $data = $stringData;
           $data .= "&signature=".$signature;
        }else {    
            $data = http_build_query($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }

        


        if($type == "GET")
            $ch = curl_init($url.'?'.$data);
        else
            $ch = curl_init($url);
        
        curl_setopt_array($ch,array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CUSTOMREQUEST => $type,
                CURLOPT_POSTFIELDS => $type != 'GET' ? $data : null,
                CURLOPT_HTTPHEADER => $header,
             //   CURLINFO_HEADER_OUT => true
        ));
        return $ch;

    }




    public function getExchangeInfo(){
        if ($this->pairInfo !== null) {
            pushLogJn($this->exchangeName, 'success', true, 'getExchangeInfo', 'Info de la paire récupérées', null, null);
            return $this->pairInfo;
        }

        $query = $this->apiQuery('GET', 'https://api.binance.com/api/v3/exchangeInfo', ['symbol' => $this->pair], [], false);
        $result = json_decode_print(curl_exec($query));
        
        if(!isset($result->symbols)){
            pushLogJn($this->exchangeName, 'error', false, 'getExchangeInfo', 'Impossible de récuperer les info de la paire', null, $result);

            return false;
        }
        $this->pairInfo = $result;
        pushLogJn($this->exchangeName, 'success', true, 'getExchangeInfo', 'Info de la paire récupérées', null, null);
        return $result;
    }

    public function isTradingEnabled(){
        $info = $this->getExchangeInfo();

        if ($info === false) {
            pushLogJn($this->exchangeName, 'error', false, 'isTradingEnabled', 'impossible d\'avoir les info de la paire', null, null);
            return false;
        }else{
            pushLogJn($this->exchangeName, 'success', (bool)($info->symbols[0]->isSpotTradingAllowed), 'isTradingEnabled', 'success', null, null);
            return (bool)($info->symbols[0]->isSpotTradingAllowed);;
        }
        
    }

    public function isWdEnabled($coin){
        $info = $this->getCoinInfo($coin);
        if ($info === false) {
            pushLogJn($this->exchangeName, 'error', false, 'isWdEnabled', 'impossible d\'avoir les info de la paire', null, null);
            return false;
        }


        $withdraw = false;
        $deposit = false;

        foreach($info as $value){
                if($value->coin == $coin){
                    foreach($value->networkList as $value2){
                        if($value2->network == $this->getNetwork($coin)){
                            $withdraw = (bool)($value2->withdrawEnable);
                            $deposit = (bool)($value2->depositEnable);
                            break 2;
                        }
                    }
                }
        }

        $logVar = array(
            'thisNetwork' => $this->getNetwork($coin),
            'coin' => $coin,
            'withdraw' => $withdraw,
            'deposit' => $deposit
        );
        if ($withdraw === true and $deposit === true) {
            pushLogJn($this->exchangeName, 'success', true, 'isWdEnabled', 'success',$logVar,null);

            return true;
        } else {
            pushLogJn($this->exchangeName, 'error', false, 'isWdEnabled', 'Withdraw ou deposit désactivé',$logVar,null);
            return false;
        }
    }



   


    public function takePriceInfo($orderList = null){
        
            if ($this->scanMode == true && $orderList === null) {
                echo ("Impossible de lancer la fonction " . $this->exchangeName . ".takePriceInfo en mode scan si orderList est null".PHP_EOL);
                return false;
            }
            $book = ($this->scanMode == false) ? $this->getOrderBook() : $this->getOrderBook($orderList);

            if (!isset($book[0])){
                if($this->scanMode == false){
                    $logVar = array();
                    pushLogJn($this->exchangeName, 'error', false, 'takePriceInfo', 'requête marche pas', $logVar, $book);
                }
    
                return false;
            }
            else{
                $priceCoin = 0;
                $priceStableCoin = 0;
                $liquidity = 0;
                $nbOrder = 0;
                $tmpArrayStable = array();
                $tmpArrayCoin = array();
                for ($i = 0; $i < count($book); $i++){
                    $tmpArrayCoin[] = $book[$i][1]; 
                    $tmpArrayStable[] = $book[$i][0];
                    $priceCoin += $book[$i][1];
                    $priceStableCoin += $book[$i][0];
                    $liquidity += ($book[$i][1] * $book[$i][0]);
                    $nbOrder++;
                    if ($liquidity >= __MIN_LIQUIDITY__)
                        break;
                }
                if ($liquidity < __MIN_LIQUIDITY__) {
                    if($this->scanMode == false){
                        $logVar = array('priceCoin' => $priceCoin,'priceStableCoin' => $priceStableCoin,'liquidity' => $liquidity,'nbOrder' => $nbOrder );
                        pushLogJn($this->exchangeName, 'error', false, 'takePriceInfo', 'La liquidité disponible est inférieure à '.__MIN_LIQUIDITY__, $logVar, null);
                    }
                    return false;
                }else{
                  /*  $this->priceCoin = $priceCoin/$nbOrder;
                    $this->priceStableCoin = $priceStableCoin/$nbOrder;
                    */
                $this->priceCoin = calculate_median($tmpArrayCoin);
                $this->priceStableCoin = calculate_median($tmpArrayStable);    
                $this->liquidity = $liquidity;
                    if($this->scanMode == false){
                        $logVar = array(
                            'thisPriceCoin' => $this->priceCoin,
                            'thisPriceStableCoin' => $this->priceStableCoin,
                            'thisLiquidity' => $this->liquidity,
                            'nbOrder' => $nbOrder 
                        );
                        pushLogJn($this->exchangeName, 'error', true, 'takePriceInfo', 'success', $logVar,null);
                    }
                    return true;
                }


            }
                
    }


    
    public function getOrderBook($orderList = null){


        if ($this->scanMode == false && $orderList !== null)
            die($this->exchangeName.".getOrderBook() Impossible d'envoyer un orderList si le scanMode n'est pas activé");

        $query = $this->orderBookQuery();
        
        $type = $this->side == 'sell' ? 'asks' : 'bids';
        $result = ($this->scanMode == true) ? $orderList : json_decode_print(curl_exec($query));

        if (isset($result->{$type}))
            return $result->{$type};
            
        else{
            if($this->scanMode == false)
                pushLogJn($this->exchangeName, 'error', false, 'getOrderBook', '', null, $result);            

            return false;
        }
    }



    


    public function getCoinAmount($type = null){
        $query = $this->apiQuery('GET','https://api.binance.com/api/v3/account',[],[],true);
        $coin = $this->side == "buy" ? $this->stableCoin : $this->coin;
        $result = json_decode_print(curl_exec($query));
        $amount = null;
        if(isset($result->balances)){
            foreach($result->balances as $value){
                if($value->asset == $coin){

                        $amount = $value->free;
                }
            }
        }

        if($amount === null){
            pushLogJn($this->exchangeName, 'error', false, "getCoinAmount","Impossible de récupérer le montant",array("coin" => $coin),$result);
            return false;
        } else {
            pushLogJn($this->exchangeName, 'success', (float) $amount, "getCoinAmount","Impossible de récupérer le montant",array("coin" => $coin),null);
            return (float) $amount;
        }
    }


    public function getCoinInfo($coin){
        if($this->allCoinInfo !== null){
            pushLogJn($this->exchangeName, 'success', true, 'getCoinInfo', 'success',array('coin' => $coin), null);
            return $this->allCoinInfo;
        }
        $query = $this->apiQuery('GET', 'https://api.binance.com/sapi/v1/capital/config/getall', [], [], true);
        $result = json_decode_print(curl_exec($query));
        if(!isset($result[0])){
            pushLogJn($this->exchangeName, 'error', false, 'getCoinInfo', 'Impossible d\'obtenir les info',array('coin' => $coin), $result);
            return false;
        }else{
            pushLogJn($this->exchangeName, 'success', true, 'getCoinInfo', 'success',array('coin' => $coin), null);
            $this->allCoinInfo = $result;
            return $result;
        }
    }

    
    public function getCoinPrecision($coin){
        $coinInfo = $this->getCoinInfo(null);
        $coin = strtoupper($coin);
        $coinPrecision = null;
        foreach($coinInfo as $value){
            if($value->coin == $coin){
                foreach($value->networkList as $value2){
                    if($value2->network == $this->getNetwork($coin)){
                        $coinPrecision = $value2->withdrawIntegerMultiple;
                        break 2;
                    }
                }
            }
        }
        
        if($coinPrecision === null){
            pushLogJn($this->exchangeName, 'error',  false, 'getCoinPrecision', "Impossible de récupérer la précision",array('coin' => $coin),null);
            return false;
        }
        if (!str_contains($coinPrecision, '.')) {
            pushLogJn($this->exchangeName, 'success', 0, 'getCoinPrecision', "",array('coin' => $coin),null);
            return 0;

        } else {
            $coinPrecision = strlen(explode('.', $coinPrecision)[1]);
            pushLogJn($this->exchangeName, 'success', $coinPrecision, 'getCoinPrecision', "",array('coin' => $coin),null);
            return $coinPrecision;
        }
    }

    public function getTradeFee($amount){
            $query = $this->apiQuery('GET', 'https://api.binance.com/sapi/v1/asset/tradeFee', ['symbol' => $this->pair], [], true);
            $result = json_decode_print(curl_exec($query));
            
            if(!isset($result[0]->takerCommission)){
                pushLogJn($this->exchangeName, 'error', false, 'getTradeFee', 'Impossible d\'avoir les frais de trade',null,$result);
                return false;
            } else {
                $fees = $result[0]->takerCommission;
                $fees *= $amount;
                $amount -= $fees;
                pushLogJn($this->exchangeName, 'success', $amount, 'getTradeFee', '',null,null);
                return $amount;
            }
    }

    public function getWithdrawFee($coin){
        $coinInfo = $this->getCoinInfo($coin);
        $fees = 0;
        if ($coinInfo === false) {
            pushLogJn($this->exchangeName, 'error',  false, 'getWithdrawFee', "Impossible d'avoir les infos de la coin",array('coin' => $coin),null);
            return false;
        }

            foreach($coinInfo as $value){
                if($value->coin == $coin){
                    foreach($value->networkList as $value2){
                        if($value2->network == $this->getNetwork($coin)){
                            $fees = $value2->withdrawFee;
                            break 2;
                        }
                    }
                }
            }
        pushLogJn($this->exchangeName, 'success',  $fees, 'getWithdrawFee', "",array('coin' => $coin),null);

         return $fees;
    }

    public function getStepSize($value){
            $info = $this->getExchangeInfo();

            if ($info === false) {
                pushLogJn($this->exchangeName, 'success', false, 'getStepSize', "Impossible d'avoir les info de l'exchange",null,null);
                return false;
            }

            $stepSize = $info->symbols[0]->filters[1]->stepSize;
            $stepSize = $this->calculateStepSize($value, $stepSize);
            pushLogJn($this->exchangeName, 'success', $stepSize, 'getStepSize', "",array('value' => $value, 'stepSize' => $info->symbols[0]->filters[1]->stepSize),null);
            return $stepSize;
    }

    public function convertCoin(){
        $quantity = $this->getCoinAmount();
        if($quantity == 0 || $quantity == false){            
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Quantité de crypto insufisante",null);
            return false;
        }
        $quantity = $this->side == "buy" ? $this->calcCoin2StableCoin($quantity) : $quantity;
        $quantity = $this->getTradeFee($quantity);

        if ($quantity === false) {
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Erreur lors du calcul des frais",null,null);
            return false;
        }


        $quantity = $this->getStepSize($quantity);

        if ($quantity === false) {
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Erreur lors du calcul du stepsize",null,null);
            return false;
        }

        $query = $this->apiQuery('POST','https://api.binance.com/api/v3/order',['side' => $this->side,'type' => 'MARKET', 'symbol' => $this->pair,'quantity' => $quantity],[],true);
        $result = json_decode_print(curl_exec($query));
        $logVar = array(
            'side' => $this->side,
            'quantity' => $quantity,
            'symbol' => $this->pair
        );

        if(isset($result->orderId)){
            $this->orderId = $result->orderId;
            pushLogJn($this->exchangeName, 'success', $result->orderId, 'convertCoin', "",$logVar,null);
            return $result->orderId;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Erreur lors de l'order",$logVar,$result);
            return false;
        }
        
    }





    // Envoyer le coin vers un autre exchange
    public function withdrawCoin($coin,$amount,$address,$tag = null){
        $coin = strtoupper($coin);
        $param = array();
        $param['coin'] = $coin;
        $param['amount'] = $amount;
        $param['address'] = $address;
        $param['network'] = $this->getNetwork($coin);
        
        if ($tag !== null)
            $param['tag'] = $tag;

        $fees = $this->getWithdrawFee($coin);
        
        if ($fees === false) {
            pushLogJn($this->exchangeName,'error',false,'withdrawCoin',"Erreur lors du calcul des withdrawFee",null,null);
            return false;
        }

        $logVar = $param;
        $precision = $this->getCoinPrecision($coin);


        if ($precision === false) {
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Impossible d'obtenir la precision",$logVar,null);
            return false;
        }


        $param['amount'] -= $fees;
        $param['amount'] = number_format($param['amount'], $precision);

        $query = $this->apiQuery('POST','https://api.binance.com/sapi/v1/capital/withdraw/apply',$param,[],true);
        $result = json_decode_print(curl_exec($query));
        $logVar['amount'] = $param['amount'];

        if (isset($result->id)) {
            $logVar['withdrawId'] = $result->id;
            pushLogJn($this->exchangeName, 'success', $result->id, 'withdrawCoin', "",$logVar,null);

            $this->withdrawId = $result->id;
            return $result->id;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Pas fonctionné",$logVar,$result);
            return false;
        }
    }
    
    
    //Check if a withdraw is finished (true is finish)
    public function checkWithdrawStatus(){
        $query = $this->apiQuery('GET','https://api.binance.com/sapi/v1/capital/withdraw/history',[],[],true);
        $result = json_decode_print(curl_exec($query));
        $withdraw = null;
        if(!isset($result[0]->id)){
            pushLogJn($this->exchangeName, 'error', false, 'checkWithdrawStatus', "Pas fonctionné",null,$result);

            return false;
        }
        foreach($result as $value){
            if($value->id == $this->withdrawId){
                $withdraw = $value;
                break;
            }
                
        }
        if($withdraw === null){
            pushLogJn($this->exchangeName, 'error', false, 'checkWithdrawStatus', "Le withdraw id n'a pas été trouvé",array('withdrawId' => $this->withdrawId),$result);
            return false;
        }else{
            if ($withdraw->status == 0 || $withdraw->status == 2 || $withdraw->status == 4)
                return __STATUS_WAITING__;
            else if ($withdraw->status == 6)
                return __STATUS_SUCCESS__;
            else{
                pushLogJn($this->exchangeName, 'error', __STATUS_CANCEL__, 'checkWithdrawStatus', "withdraw annulé",array('withdrawId' => $this->withdrawId),$withdraw);
                return __STATUS_CANCEL__;
            }
                
        }
        
    }

     //Check if a conversion coin-stablecoin or stablecoin-coin is finished (aussi appelé order par exemple sur binance)
     public function checkConvertStatus(){
        $query = $this->apiQuery('GET','https://api.binance.com/api/v3/allOrders',['symbol' => $this->pair],[],true);
        $result = json_decode_print(curl_exec($query));

        if(isset($result[0]->symbol)){
                $order = null;
                foreach($result as $value){
                    if($value->orderId == $this->orderId){
                        $order = $value;
                        break;
                    }
                }

                if($order === null){
                    pushLogJn($this->exchangeName, 'error', false, 'checkConvertStatus', "L'order n'existe pas",array('orderId' => $this->orderId),null);
                    return false;
                }else{
                    if ($order->status == "FILLED")
                        return __STATUS_SUCCESS__;
                    else if($order->status == "PARTIALLY_FILLED" OR $order->status == "NEW")
                        return __STATUS_WAITING__;
                    else {
                        pushLogJn($this->exchangeName, 'error', __STATUS_CANCEL__, 'checkConvertStatus', "Order annulé",array('orderId' => $this->orderId),$order);
                        return __STATUS_CANCEL__;
                    }
                }

        } else {
            pushLogJn($this->exchangeName, 'error', false, 'checkConvertStatus', "Impossible de check l'order",array('orderId' => $this->orderId),$result);

            return false;
        }
        
    }

    
    /*********************************************
    ********************************************/

}