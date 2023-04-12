<?php

class Kucoin extends AbstractAPI{


    public function __construct($coin,$stablecoin){
        parent::__construct();

        $this->coin = strtoupper($coin);
        $this->stableCoin = strtoupper($stablecoin);
        $this->pair = $this->coin."-".$this->stableCoin;
    
    }

    public function getExName(){
        return get_class($this);
    }

    //Fonction qui build la requête api
    public function apiQuery(string $type,string $url,array $data,array $header,bool $enableSigned){
            $timestamp = time() * 1000;
            $header[] = 'KC-API-KEY: '. __LISTKEY__['Kucoin']['api'];
            $header[] = 'KC-API-TIMESTAMP: '. $timestamp; 
            $header[] = 'KC-API-PASSPHRASE: '. __LISTKEY__['Kucoin']['passphrase'];
            $header[] = 'KC-API-KEY-VERSION: v2';

            $header[] = 'Content-type: application/json';

            // Créez une chaîne de données de requête à partir de $data
            $queryString = http_build_query($data);
            $urlPath = explode('https://api.kucoin.com', $url)[1];

            
            if(count($data) <= 0)
                $what = $timestamp . $type . $urlPath;
            else
                $what = $timestamp . $type . $urlPath . "?". $queryString;

            if ($type != 'GET')
                $what = $timestamp . $type . $urlPath . json_encode($data);

            // Créez la chaîne de signature
            $signature = base64_encode(hash_hmac('sha256', $what, __LISTKEY__['Kucoin']['secret'],true));
            $header[] = 'KC-API-SIGN: '. $signature;
            
            if($type == "GET")
                $ch = curl_init($url."?".$queryString);
            else
                $ch = curl_init($url);
            // Configurez les options cURL
            $options = array(                
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => __USER_AGENT__,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CUSTOMREQUEST => $type,
                CURLOPT_POSTFIELDS => $type != 'GET' ? json_encode($data) : null,
                CURLOPT_HTTPHEADER => $header,
             //   CURLINFO_HEADER_OUT => true
            );

        curl_setopt_array($ch, $options);
        return $ch;


    }


    public function takePriceInfo($orderList = null){
        if ($this->scanMode == true && $orderList === null) {
            echo ("Impossible de lancer la fonction " . $this->exchangeName . ".takePriceInfo en mode scan si orderList est null".PHP_EOL);
            return false;
        }
        $book = ($this->scanMode == false) ? $this->getOrderBook() : $this->getOrderBook($orderList);



        if (!isset($book[0])) {
            
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
                    pushLogJn($this->exchangeName, 'error', false, 'takePriceInfo', 'La liquidité disponible est inférieure à '.__MIN_LIQUIDITY__, $logVar, $book);
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

    public function innerTransfer($type,$coin,$amount){
        $coin = strtoupper($coin);
        if($type == "trade"){
            $from = 'main';
            $to = 'trade';
        }else{
            $from = 'trade';
            $to = 'main';
        }
        $query = $this->apiQuery('POST', 'https://api.kucoin.com/api/v2/accounts/inner-transfer', ['from' => $from, 'to' => $to, 'currency' => $coin, 'amount' => $amount, 'clientOid' => __KUCOIN_CLIENT_OID__ ], [], true);
        $result = json_decode_print(curl_exec($query));
        
        $logVar = array(
            'type' => $type,
            'coin' => $coin,
            'amount' => $amount,
            'from' => $from,
            'to' => $to 
        );
        if (isset($result->data->orderId)) {
  
            pushLogJn($this->exchangeName, 'success', true, 'innerTransfer', 'success', $logVar,null);
            
            return true;
        }
        else {
  
            pushLogJn($this->exchangeName, 'error', false, 'innerTransfer', 'orderId introuvable', $logVar,$result);            
            return false;
        }
    }

    public function launchTransfer($type,$coin){
        $coin = ($this->side == "buy") ? $this->stableCoin : $this->coin;
        $amount = $this->getCoinAmount4Transfer($coin);


        if ($amount['trade'] == 0 && $amount['main'] == 0) {
            $logVar = array(
                'coin' => $coin,
                'type' => $type,
                'amount' => $amount
            );
            pushLogJn($this->exchangeName, 'error', false, 'launchTransfer', 'pas d\'argent dans le portefeuille', $logVar,null);            

            return false;
        }


        if($type == 'trade'){

            if ($amount['trade'] > $amount['main']){
                $logVar = array(
                    'coin' => $coin,
                    'type' => $type,
                    'amount' => $amount
                );
                pushLogJn($this->exchangeName, 'success', true, 'launchTransfer', 'success', $logVar, null);   
            
                return true;
            }

            if($amount['main'] >= $amount['trade']){
                $amount = number_format($amount['main'],$this->getCoinPrecision($coin));
                $transfer = $this->innerTransfer($type,$coin,$amount);
                sleep(2);

                $logVar = array(
                    'coin' => $amount,
                    'type' => $type,
                    'amount' => $amount
                );
                pushLogJn($this->exchangeName, 'success', $transfer, 'launchTransfer', 'success', $logVar, null);
                return $transfer;
            }
        }

        if($type == 'main'){
            if ($amount['main'] > $amount['trade']){

                $logVar = array(
                    'coin' => $amount,
                    'type' => $type,
                    'amount' => $amount
                );
                pushLogJn($this->exchangeName, 'success', true, 'launchTransfer', 'success', $logVar, null);   
                return true;
            }

                
            if($amount['trade'] >= $amount['main']){
                $amount = number_format($amount['trade'],$this->getCoinPrecision($coin));
                $transfer = $this->innerTransfer($type,$coin,$amount);
                sleep(2);

                $logVar = array(
                    'coin' => $amount,
                    'type' => $type,
                    'amount' => $amount
                );
                pushLogJn($this->exchangeName, 'maybe', $transfer, 'launchTransfer', 'maybe', $logVar, null);

                return $transfer;            
            }
        }

        $logVar = array(
            'coin' => $amount,
            'type' => $type,
            'amount' => $amount
        );
        pushLogJn($this->exchangeName, 'error', false, 'launchTransfer', '', $logVar, null);

        return false;
        
    }


    public function orderBookQuery(){
        return $this->apiQuery('GET', 'https://api.kucoin.com/api/v3/market/orderbook/level2', ['symbol' => $this->pair], [], true);
    }


    public function getOrderBook($orderList = null){
        if ($this->scanMode == false && $orderList !== null)
            die($this->exchangeName.".getOrderBook() Impossible d'envoyer un orderList si le scanMode n'est pas activé");

        $query = $this->orderBookQuery();

        $type = $this->side == 'sell' ? 'asks' : 'bids';
        $result = ($this->scanMode == true) ? $orderList : json_decode_print(curl_exec($query));
        if (isset($result->data->{$type}))
            return $result->data->{$type};
            
        else{

            if($this->scanMode == false)
                        pushLogJn($this->exchangeName, 'error', false, 'getOrderBook', '', null, $result);            
            return false;
        }

    }

     /**
     * Avoir les informations sur une paire
     *
     * @return void
     */
    public function getExchangeInfo(){
        if ($this->pairInfo !== null) {
            pushLogJn($this->exchangeName, 'success', true, 'getExchangeInfo', 'Info de la paire récupérées', null, null);
            return $this->pairInfo;
        }

        $query = $this->apiQuery('GET', 'https://api.kucoin.com/api/v2/symbols', [], [], false);
        $result = json_decode_print(curl_exec($query));

        if(!isset($result->data[0])){
            pushLogJn($this->exchangeName, 'error', false, 'getExchangeInfo', 'Impossible de récuperer les info de la paire', null, $result);
            return false;
        }

        $pairInfo = null;
        foreach($result->data as $value){
            if ($value->symbol == $this->pair){
                $pairInfo = $value;
                break;
            }
        }

        if($pairInfo === null){
            pushLogJn($this->exchangeName, 'error', false, 'getExchangeInfo', 'info de la paire introuvable', null, null);
            return false;
        }
        pushLogJn($this->exchangeName, 'success', true, 'getExchangeInfo', 'paire trouvée', null, null);

        $this->pairInfo = $pairInfo;
        return $pairInfo;

    }

 
        
 
    /**
     * Vérifie si on peut trade une pair
     *
     * @param  mixed $coin
     * @return void
     */
    public function isTradingEnabled(){
        $info = $this->getExchangeInfo();

        if ($info === false) {
            pushLogJn($this->exchangeName, 'error', false, 'isTradingEnabled', 'impossible d\'avoir les info de la paire', null, null);
            return false;
        }

        pushLogJn($this->exchangeName, 'success', true, 'isTradingEnabled', 'success', null, null);
        return (bool) $info->enableTrading;

    }   

    public function getCoinInfo($coin){
        $coin = strtoupper($coin);
        $query = $this->apiQuery('GET', 'https://api.kucoin.com/api/v2/currencies/'.$coin, [], [], true);
        $result = json_decode_print(curl_exec($query));
        
        if (!isset($result->data->precision)) {
            pushLogJn($this->exchangeName, 'error', false, 'getCoinInfo', 'Impossible d\'obtenir les info',array('coin' => $coin), $result);
            return false;
        }else{
            
            pushLogJn($this->exchangeName, 'success', true, 'getCoinInfo', 'success',array('coin' => $coin), null);
            return $result->data;
        }
    }

       /**
     * Vérifie si on peut withdraw une coin
     *
     * @param  mixed $coin
     * @return void
     */
    public function isWdEnabled($coin){
        $coin = strtoupper($coin);
        $info = $this->getCoinInfo($coin);
        if ($info === false) {
            pushLogJn($this->exchangeName, 'error', false, 'isWdEnabled', 'impossible d\'avoir les info de la paire', null, null);
            return false;
        }

        $withdraw = false;
        $deposit = false;

        foreach($info->chains as $value){
            if($value->chain == $this->getNetwork($coin)){
                 $withdraw = (bool) $value->isWithdrawEnabled;
                 $deposit = (bool) $value->isDepositEnabled;
                 break;
            }
        }
        $logVar = array(
            'thisNetwork' => $this->getNetwork($coin),
            'coin' => $coin,
            'withdraw' => $withdraw,
            'deposit' => $deposit
        );
        if ($withdraw === true and $deposit === true){
            pushLogJn($this->exchangeName, 'success', true, 'isWdEnabled', 'success',$logVar,null);
            return true;
        }
        else{
            pushLogJn($this->exchangeName, 'error', false, 'isWdEnabled', 'Withdraw ou deposit désactivé',$logVar,null);
            return false;
        }

    }

    
  

    public function getCoinAmount($type = null){
        $query = $this->apiQuery('GET','https://api.kucoin.com/api/v1/accounts',[],[],true);
        $coin = $this->side == "buy" ? $this->stableCoin : $this->coin;
        $result = json_decode_print(curl_exec($query));
        $amount = null;

        $logVar['coin'] = $coin;
        if(isset($result->data)){
            foreach($result->data as $value){
                if ($value->currency == $coin && $value->type == $type) {
                    $amount = $value->available;
                    break;
                }

            }
            if ($amount === null)
                $amount = 0;

                $logVar['amount'] = $amount;
                pushLogJn($this->exchangeName, 'success', (double)$amount, 'getCoinAmount', '',$logVar,null);
            return (double)$amount;
        }else{

            
            pushLogJn($this->exchangeName, 'error', false, 'getCoinAmount', '',$logVar,$result);
            return false;
        }

    }

    public function getCoinAmount4Transfer($coin){
        $query = $this->apiQuery('GET','https://api.kucoin.com/api/v1/accounts',[],[],true);
        $coin = strtoupper($coin);
        $result = json_decode_print(curl_exec($query));
        $amount['main'] = 0;
        $amount['trade'] = 0;

        $logVar['coin'] = $coin;
        if(isset($result->data)){
            foreach($result->data as $value){
                if ($value->currency == $coin && $value->type == 'main') 
                    $amount['main'] = $value->available;

                if ($value->currency == $coin && $value->type == 'trade') 
                    $amount['trade'] = $value->available;
                    
    
            }
            pushLogJn($this->exchangeName, 'success', $amount, 'getCoinAmount4Transfer', '',$logVar,null);

        }
        else
            pushLogJn($this->exchangeName, 'error', $amount, 'getCoinAmount4Transfer', '',$logVar,$result);


        return $amount;

    }

    

    public function getTradeFee($amount){
        // La fonction pour kucoin est aussi longue par sécurité parce que il y a présence d'un param feeCurrency dans l'api
        // Au cas ou le feeCurrency change bah kucoin le prend en compte
        $query = $this->apiQuery('GET', 'https://api.kucoin.com/api/v1/base-fee', ['symbol' => $this->pair], [], true);
        $result = json_decode_print(curl_exec($query));

        if (!isset($result->data->takerFeeRate)){
            pushLogJn($this->exchangeName, 'error', false, 'getTradeFee', 'Impossible d\'avoir les frais de trade',null,$result);
            return false;
        }

        $fee = $result->data->takerFeeRate;
        $feeCurrency = $this->getExchangeInfo();

        if ($feeCurrency === false) {
            pushLogJn($this->exchangeName, 'error', false, 'getTradeFee', 'Impossible d\'avoir les infos de l\'exchange',null,null);
            return false;
        }
        if($feeCurrency->feeCurrency == $this->stableCoin){
            $amountTotal = $this->calcCoin2StableCoin($amount);
            $fee *= $amountTotal;
            $amountTotal -= $fee;
            $amountTotal = $this->calcCoin2StableCoin($amountTotal);

            pushLogJn($this->exchangeName, 'success', $amountTotal, 'getTradeFee', '',null,null);
            return $amountTotal;
        }
        else if($feeCurrency->feeCurrency == $this->coin){
            $fee *= $amount;
            $amount -= $fee;

            pushLogJn($this->exchangeName, 'success', $amount, 'getTradeFee', '',null,null);
            return $amount;

        } else {
            pushLogJn($this->exchangeName, 'success', false, 'getTradeFee', '',null,null);

            return false;
        }
    }

    public function getStepSize($value){
        $info = $this->getExchangeInfo();

        if ($info === false) {
            pushLogJn($this->exchangeName, 'success', false, 'getStepSize', "Impossible d'avoir les info de l'exchange",null,null);
            return false;
        }

        $stepSize = $info->baseIncrement;
        $stepSize = $this->calculateStepSize($value, $stepSize);

        pushLogJn($this->exchangeName, 'success', $stepSize, 'getStepSize', "",array('value' => $value, 'baseIncrement' => $info->baseIncrement),null);
        return $stepSize;

    }
    
    public function convertCoin(){


        //Si le portefeuille est unique il n'y a pas de trade/main il faut se référer à la fonction convert de Binance
        $transfer = $this->launchTransfer('trade',($this->side == 'buy') ? $this->stableCoin : $this->coin);

        if ($transfer === false){
            sleep(4);
            $transfer = $this->launchTransfer('trade',($this->side == 'buy') ? $this->stableCoin : $this->coin);

            if ($transfer === false) {
                pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Echec du transfert portefeuille",null,null);

                return false;
            }
        }

     
        $quantity = $this->getCoinAmount('trade');
        
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

        $query = $this->apiQuery('POST','https://api.kucoin.com/api/v1/orders',['side' => $this->side,'type' => 'market', 'symbol' => $this->pair,'size' => $quantity,'clientOid' => __KUCOIN_CLIENT_OID__],[],true);
        $result = json_decode_print(curl_exec($query));

        $logVar = array(
            'side' => $this->side,
            'size' => $quantity,
            'symbol' => $this->pair
        );
        if(isset($result->data->orderId)){
            $this->orderId = $result->data->orderId;            
            pushLogJn($this->exchangeName, 'success', $result->data->orderId, 'convertCoin', "",$logVar,null);
            return $result->data->orderId;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Erreur lors de l'order",$logVar,$result);
            return false;
        }
    }

    public function checkConvertStatus(){
        $query = $this->apiQuery('GET','https://api.kucoin.com/api/v1/orders/'.$this->orderId,[],[],true);
        $result = json_decode_print(curl_exec($query));
        if(isset($result->data)){
                 $order = $result->data;

                    if ($order->isActive == false && $order->cancelExist == false)
                        return __STATUS_SUCCESS__;
                    else if($order->isActive == true)
                        return __STATUS_WAITING__;
                    else {
                        pushLogJn($this->exchangeName, 'error', __STATUS_CANCEL__, 'checkConvertStatus', "Order annulé",array('orderId' => $this->orderId),$order);
                        return __STATUS_CANCEL__;
                    }
                

        } else {
            pushLogJn($this->exchangeName, 'error', false, 'checkConvertStatus', "Impossible de check l'order",array('orderId' => $this->orderId),$result);
            return false;
        }
    }


  



    public function getCoinPrecision($coin){
        $coin = strtoupper($coin);
        $data = $this->getCoinInfo($coin);
        if ($data === false) {
            pushLogJn($this->exchangeName, 'error', false, 'getCoinPrecision', "Impossible d'avoir les infos de la coin",array('coin' => $coin),null);
            return false;
        }

        pushLogJn($this->exchangeName, 'success',  $data->precision, 'getCoinPrecision', "",array('coin' => $coin),null);

        return $data->precision; 
    }

    public function getWithdrawFee($coin){
        $coin = strtoupper($coin);
        $info = $this->getCoinInfo($coin);

        if ($info === false) {
            pushLogJn($this->exchangeName, 'error',  false, 'getWithdrawFee', "Impossible d'avoir les infos de la coin",array('coin' => $coin),null);
            return false;
        }

        $fee = 0;
        foreach($info->chains as $value){
            if($value->chain == $this->getNetwork($coin)){
                $fee = $value->withdrawalMinFee;
                break;
            }
        }
        
        pushLogJn($this->exchangeName, 'success',  $fee, 'getWithdrawFee', "",array('coin' => $coin),null);
        return $fee;
        
    }    

    
    public function withdrawCoin($coin,$amount,$address,$tag=null){


        $coin = strtoupper($coin);
        $transfer = $this->launchTransfer('main',$coin);


        if ($transfer === false){
            sleep(2);
            $transfer = $this->launchTransfer('main',$coin);

            if ($transfer === false) {
                pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Echec du transfert portefeuille",null,null);
                return false;
            }
        }
            

        $param = array();
        $param['currency'] = $coin;
        $param['amount'] = $amount;
        $param['address'] = $address;
        $param['chain'] = $this->getNetwork($coin);

        
        if ($tag !== null)
            $param['memo'] = $tag;

        //Pas besoin de déduire les fees pour le withdrawal kucoin s'en charge lui même



        $logVar = $param;
        $precision = $this->getCoinPrecision($coin);


        if ($precision === false) {
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Impossible d'obtenir la precision",$logVar,null);
            return false;
        }

        $param['amount'] = number_format($param['amount'], $precision);
        $query = $this->apiQuery('POST','https://api.kucoin.com/api/v1/withdrawals',$param,[],true);

        $result = json_decode_print(curl_exec($query));
        
        $logVar['amount'] = $param['amount']; 
        if (isset($result->data->withdrawalId)) {
            $logVar['withdrawId'] = $result->data->withdrawalId;
            
            pushLogJn($this->exchangeName, 'success', $result->data->withdrawalId, 'withdrawCoin', "",$logVar,null);
            
            $this->withdrawId = $result->data->withdrawalId;
            return $result->data->withdrawalId;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Pas fonctionné",$logVar,$result);
            return false;
        }
    }
    


    public function checkWithdrawStatus(){
        $query = $this->apiQuery('GET','https://api.kucoin.com/api/v1/withdrawals',[],[],true);
        $result = json_decode_print(curl_exec($query));
        $withdraw = null;
        if(!isset($result->data->items)){
            pushLogJn($this->exchangeName, 'error', false, 'checkWithdrawStatus', "Pas fonctionné",null,$result);

            return false;
        }
        foreach($result->data->items as $value){
            if($value->id == $this->withdrawId){
                $withdraw = $value;
                break;
            }
                
        }
        if($withdraw === null){
            pushLogJn($this->exchangeName, 'error', false, 'checkWithdrawStatus', "Le withdraw id n'a pas été trouvé",array('withdrawId' => $this->withdrawId),$result);
            return false;
        }else{
            if ($withdraw->status == 'PROCESSING' || $withdraw->status == 'WALLET_PROCESSING')
                return __STATUS_WAITING__;
            else if ($withdraw->status == 'SUCCESS')
                return __STATUS_SUCCESS__;
            else{
                pushLogJn($this->exchangeName, 'error', __STATUS_CANCEL__, 'checkWithdrawStatus', "withdraw annulé",array('withdrawId' => $this->withdrawId),$withdraw);
                return __STATUS_CANCEL__;
            }
                
        }
    }

 


  
    

    
  
   
  
}