<?php

class Okex extends AbstractAPI{


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
            $timestamp = gmdate('Y-m-d\TH:i:s\.000\Z');
            $header[] = 'OK-ACCESS-KEY: '. __LISTKEY__['Okex']['api'];
            $header[] = 'OK-ACCESS-TIMESTAMP: '. $timestamp; 
            $header[] = 'OK-ACCESS-PASSPHRASE: '. __LISTKEY__['Okex']['passphrase'];

            $header[] = 'Content-type: application/json';

            // Créez une chaîne de données de requête à partir de $data
            $queryString = http_build_query($data);


            $urlPath = explode('https://www.okx.com', $url)[1];
            if(count($data) <= 0)
                $what = $timestamp . $type . $urlPath;
            else
                $what = $timestamp . $type . $urlPath . "?". $queryString;

            if ($type != 'GET')
                $what = $timestamp . $type . $urlPath . json_encode($data);
            
            // Créez la chaîne de signature
            $signature = base64_encode(hash_hmac('sha256', $what, __LISTKEY__['Okex']['secret'],true));
            $header[] = 'OK-ACCESS-SIGN: '. $signature;

        
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
        if ($this->scanMode == true && $orderList == null) {
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


    
    public function orderBookQuery(){
        return $this->apiQuery('GET', 'https://www.okx.com/api/v5/market/books', ['instId' => $this->pair,'sz' => '400'], [], true);
    }


    public function getOrderBook($orderList = null){
        if ($this->scanMode == false && $orderList != null)
            die($this->exchangeName.".getOrderBook() Impossible d'envoyer un orderList si le scanMode n'est pas activé");

        $query = $this->orderBookQuery();

    
        $type = $this->side == 'sell' ? 'asks' : 'bids';
        $result = ($this->scanMode == true) ? $orderList : json_decode_print(curl_exec($query));

        
        if (isset($result->data[0]->{$type}))
            return $result->data[0]->{$type};
            
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

        $query = $this->apiQuery('GET', 'https://www.okx.com/api/v5/public/instruments', ['instType' => 'SPOT','instId' => $this->pair], [], false);
        $result = json_decode_print(curl_exec($query));
        if(!isset($result->data[0]->instId)){
            pushLogJn($this->exchangeName, 'error', false, 'getExchangeInfo', 'Impossible de récuperer les info de la paire', null, $result);

            return false;
        }


        pushLogJn($this->exchangeName, 'success', true, 'getExchangeInfo', 'Info de la paire récupérées', null, null);
        $this->pairInfo = $result->data[0];
        return $result->data[0];
    }

     
        
 
    /**
     * Vérifie si on peut trade une pair
     *
     * @param  mixed $coin
     * @return void
     */
    public function isTradingEnabled(){
        $info = $this->getExchangeInfo();
        if ($info !== false) {
            pushLogJn($this->exchangeName, 'success', true, 'isTradingEnabled', 'success', null, null);
            return true;
        } else {
            pushLogJn($this->exchangeName, 'error', false, 'isTradingEnabled', 'impossible d\'avoir les info de la paire', null, null);
            return false;
        }
    }   

    
    /**
     * Obtenir le nombre de coin que l'on dispose exemple avec la paire XRPUSDT
     * Si notre side est buy : On obtient le montant d'USDT que l'on dispose
     * Si notre side est sell: On obtient le montant d'XRP que l'on dispose
     * @return void
     */
    public function getCoinAmount($type = null){
        $coin = $this->side == "buy" ? $this->stableCoin : $this->coin;
        
        $info = $this->getCoinAmount4Transfer($coin);

        if ($info === false) {
            pushLogJn($this->exchangeName, 'error', false, "getCoinAmount","Impossible d'avoir les info du coin",array("coin" => $coin),null);
            return false;
        } else {
            pushLogJn($this->exchangeName, 'success', $info[$type], 'getCoinAmount', '',array('type' => $type,'coin' => $coin),null);
            return $info[$type];
        }
    }

    
    /**
     * Récupère les informations sur une Coin (pour l'api de binance ça récupère pour toutes les coin puis on les traites individuellement)
     *
     * @param  mixed $coin
     * @return void
     */
    public function getCoinInfo($coin){
        $coin = strtoupper($coin);
        $query = $this->apiQuery('GET', 'https://www.okx.com/api/v5/asset/currencies', ['ccy' => $coin], [], true);
        $result = json_decode_print(curl_exec($query));
      
        if (!isset($result->data[0])) {
            pushLogJn($this->exchangeName, 'error', false, 'getCoinInfo', 'Impossible d\'obtenir les info',array('coin' => $coin), $result);
            return false;
        }else{
            foreach($result->data as $value){
                if($value->chain == $this->getNetwork($coin)){
                    pushLogJn($this->exchangeName, 'success', $value, 'getCoinInfo', 'success',array('coin' => $coin,'network' => $this->getNetwork($coin)),null);
                    return $value;

                }
            }
            pushLogJn($this->exchangeName, 'success', true, 'getCoinInfo', 'success',array('coin' => $coin), $result);
            return false;
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
        $withdraw = (bool)$info->canWd;
        $deposit = (bool)$info->canDep;

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

    
    /**
     * Récupère le nombre de decimal que la crypto exige
     *
     * @param  mixed $coin
     * @return void
     */
    public function getCoinPrecision($coin){
        $coin = strtoupper($coin);
        $data = $this->getCoinInfo($coin);
        if ($data === false) {
            pushLogJn($this->exchangeName, 'error', false, 'getCoinPrecision', "Impossible d'avoir les infos de la coin",array('coin' => $coin),null);
            return false;
        }
        pushLogJn($this->exchangeName, 'success',  $data->wdTickSz, 'getCoinPrecision', "",array('coin' => $coin),null);

        return $data->wdTickSz;

    }
    
    
      
    public function getCoinAmount4Transfer($coin){
        $coin = strtoupper($coin);
        $query = $this->apiQuery('GET','https://www.okx.com/api/v5/account/balance',['ccy' => $coin],[],true);
        $result = json_decode_print(curl_exec($query));
        $amount['trade'] = 0;
        $amount['main'] = 0;
        $logVar['coin'] = $coin;
        if(isset($result->data[0]->details[0]->availBal))
            $amount['trade'] = $result->data[0]->details[0]->availBal;
        else{
            pushLogJn($this->exchangeName, 'error', $amount, 'getCoinAmount4Transfer', '',$logVar,$result);

        }

        $query = $this->apiQuery('GET','https://www.okx.com/api/v5/asset/balances',['ccy' => $coin],[],true);
        $result = json_decode_print(curl_exec($query));
        

        if(isset($result->data[0]->availBal))
            $amount['main'] = $result->data[0]->availBal;
        else{
            pushLogJn($this->exchangeName, 'error', $amount, 'getCoinAmount4Transfer', '',$logVar,$result);
        }
   
        pushLogJn($this->exchangeName, 'success', $amount, 'getCoinAmount4Transfer', '',$logVar,null);
        return $amount;
    }

    public function innerTransfer($type,$coin,$amount){
        $coin = strtoupper($coin);
        if($type == "trade"){
            $from = 6; //main
            $to = 18; // trade
        }else{
            $from = 18; // trade
            $to = 6; // main
        }
        $query = $this->apiQuery('POST', 'https://www.okx.com/api/v5/asset/transfer', ['from' => $from, 'to' => $to, 'ccy' => $coin, 'amt' => $amount], [], true);
        $result = json_decode_print(curl_exec($query));
        $logVar = array(
            'type' => $type,
            'coin' => $coin,
            'amount' => $amount,
            'from' => $from,
            'to' => $to 
        );
        if (isset($result->data[0]->ccy)) {
            pushLogJn($this->exchangeName, 'success', true, 'innerTransfer', 'success', $logVar,null);
            return true;
        }
        else {
            pushLogJn($this->exchangeName, 'error', false, 'innerTransfer', 'orderId introuvable', $logVar,$result);            
            return false;
        }
   
    }

    public function launchTransfer($type, $coin)
    {
        $coin = strtoupper($coin);
        $amount = $this->getCoinAmount4Transfer($coin);

        if ($amount['trade'] == 0 && $amount['main'] == 0) {
            $logVar = array('coin' => $coin,'type' => $type,'amount' => $amount);
            pushLogJn($this->exchangeName, 'error', false, 'launchTransfer', 'pas d\'argent dans le portefeuille', $logVar,null);            

            return false;
        }
            
        if($type == 'trade'){
            if ($amount['trade'] > $amount['main']){
                $logVar = array('coin' => $coin,'type' => $type,'amount' => $amount);
                pushLogJn($this->exchangeName, 'success', true, 'launchTransfer', 'success', $logVar, null);   
            
                return true;
            }

            if($amount['main'] >= $amount['trade']){
                    $amount = number_format($amount['main'],$this->getCoinPrecision($coin));
                    $transfer = $this->innerTransfer($type,$coin,$amount);
                    sleep(2);
                    
                    $logVar = array('coin' => $amount,'type' => $type,'amount' => $amount);
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

                
                $logVar = array('coin' => $amount,'type' => $type,'amount' => $amount);
                pushLogJn($this->exchangeName, 'maybe', $transfer, 'launchTransfer', 'maybe', $logVar, null);
                return $transfer;            
            }
        }
        $logVar = array('coin' => $amount,'type' => $type,'amount' => $amount);
        pushLogJn($this->exchangeName, 'error', false, 'launchTransfer', '', $logVar, null);
        return false;
    }
    /**
     * Récupérer les frais de trading
     * Le $amount est toujours celui de l'asset de base pour la paire XRP-USDT ce sera donc le montant de XRP
     * @return void
     */
    public function getTradeFee($amount){
        $query = $this->apiQuery('GET', 'https://www.okx.com/api/v5/account/trade-fee', ['instType' => "SPOT", 'instId' => $this->pair], [], true);
        $result = json_decode_print(curl_exec($query));
        if(!isset($result->data[0]->taker)){
            pushLogJn($this->exchangeName, 'error', false, 'getTradeFee', 'Impossible d\'avoir les frais de trade',null,$result);
            return false;
        }
        $amount = $this->calcCoin2StableCoin($amount);
        $fee = $amount * abs($result->data[0]->taker);
        $amount = $amount - $fee;
        $amount = $this->calcStableCoin2Coin($amount);
        pushLogJn($this->exchangeName, 'success', $amount, 'getTradeFee', '',null,null);
        return $amount;
    }

    /**
     * Avoir le stepsize pour éviter les erreurs de lots lors d'un buy market
     *
     * @return void
     */
    public function getStepSize($value){
        $info = $this->getExchangeInfo();

        if ($info === false) {
            pushLogJn($this->exchangeName, 'success', false, 'getStepSize', "Impossible d'avoir les info de l'exchange",null,null);
            return false;
        }


        $stepSize = $info->lotSz;
        $stepSize = $this->calculateStepSize($value, $stepSize);
        pushLogJn($this->exchangeName, 'success', $stepSize, 'getStepSize', "",array('value' => $value, 'lotSz' => $info->lotSz),null);
        return $stepSize;
    }

    /**
     * Permet de faire un order 
     * Le side peut etre change avec sideToSeller() ou sideToBuyer() en conséquence la quantité calculée changera et coinAmount aussi
     * @return void
     */
    public function convertCoin(){
        
        //Si le portefeuille est unique il n'y a pas de trade/main il faut se référer à la fonction convert de Binance
        $transfer = $this->launchTransfer('trade',($this->side == 'buy') ? $this->stableCoin : $this->coin);

        if ($transfer === false){
            sleep(2);
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

        $query = $this->apiQuery('POST','https://www.okx.com/api/v5/trade/order',['side' => $this->side,'ordType' => 'market', 'instId' => $this->pair,'sz' => $quantity,'tdMode' => 'cash','tgtCcy' => 'base_ccy'],[],true);
        $result = json_decode_print(curl_exec($query));

        $logVar = array(
            'side' => $this->side,
            'size' => $quantity,
            'symbol' => $this->pair
        );
        if(isset($result->data[0]->ordId)){
            $this->orderId = $result->data[0]->ordId;
            pushLogJn($this->exchangeName, 'success', $result->data[0]->ordId, 'convertCoin', "",$logVar,null);
            return $result->data[0]->ordId;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'convertCoin', "Erreur lors de l'order",$logVar,$result);
            return false;
        }

    }


    //Check if a conversion coin-stablecoin or stablecoin-coin is finished (aussi appelé order par exemple sur binance)
    public function checkConvertStatus(){

        $query = $this->apiQuery('GET','https://www.okx.com/api/v5/trade/order',['ordId' => $this->orderId,'instId' => $this->pair],[],true);
        $result = json_decode_print(curl_exec($query));
        if(isset($result->data[0])){
                 $order = $result->data[0];
                    if ($order->state == 'filled')
                        return __STATUS_SUCCESS__;
                    else if($order->state == 'partially_filled' || $order->state == 'live')
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



    public function getWithdrawFee($coin){
        $coin = strtoupper($coin);
        $info = $this->getCoinInfo($coin);

        if ($info === false) {
            pushLogJn($this->exchangeName, 'error',  false, 'getWithdrawFee', "Impossible d'avoir les infos de la coin",array('coin' => $coin),null);
            return false;
        }

        $fee = $info->minFee;
        
        pushLogJn($this->exchangeName, 'success',  $fee, 'getWithdrawFee', "",array('coin' => $coin),null);

        return $fee;
        
    }

    // Envoyer le coin vers un autre exchange
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

        $fee = $this->getWithdrawFee($coin);

        if ($fee === false) {
            pushLogJn($this->exchangeName,'error',false,'withdrawCoin',"Erreur lors du calcul des withdrawFee",null,null);
            return false;
        }
            
        $param = array();
        $param['ccy'] = $coin;
        $param['amt'] = $amount - $fee;
        $param['fee'] = $fee;
        $param['toAddr'] = $address;
        $param['chain'] = $this->getNetwork($coin);
        $param['dest'] = 4;

        
        if ($tag !== null)
            $param['toAddr'] = $param['toAddr'].":".$tag;

        $logVar = $param;
        $precision = $this->getCoinPrecision($coin);

        
        if ($precision === false) {
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Impossible d'obtenir la precision",$logVar,null);
            return false;
        }

        $param['amt'] = number_format($param['amt'], $precision);
        $query = $this->apiQuery('POST','https://www.okx.com/api/v5/asset/withdrawal',$param,[],true);

        $result = json_decode_print(curl_exec($query));
        $logVar['amount'] = $param['amt']; 

        if (isset($result->data[0]->wdId)) {
            $logVar['withdrawId'] = $result->data[0]->wdId;
            
            pushLogJn($this->exchangeName, 'success', $result->data[0]->wdId, 'withdrawCoin', "",$logVar,null);
            $this->withdrawId = $result->data[0]->wdId;
            return $result->data[0]->wdId;
        }else{
            pushLogJn($this->exchangeName, 'error', false, 'withdrawCoin', "Pas fonctionné",$logVar,$result);
            return false;
        }


    }
    


    //Check if a withdraw is finished
    public function checkWithdrawStatus(){
        $query = $this->apiQuery('GET','https://www.okx.com/api/v1/withdrawals',[],[],true);
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