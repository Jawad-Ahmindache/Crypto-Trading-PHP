<?php

abstract class AbstractAPI {

    
    public $actualPrice = null;

    public $chainNetwork;
    public $pair = "";

    public $side;
    public $exchangeName;
    public $coin;
    public $stableCoin;

    public $scanMode = false;
    public $pairInfo = null;

    public $priceCoin;
    public $priceStableCoin;

    public $addressList;
    public $liquidity;
    
    public $orderId = null;
    public $withdrawId = null;

    public function __construct(){
        
        $this->exchangeName = get_class($this);
        $this->chainNetwork = __CHAIN_NETWORK__[$this->exchangeName];
        $this->addressList = __ADDRESS_LIST__[$this->exchangeName];

    }


      /************************************
        ******FONCTION UTILITAIRE ******
     ************************************
     ***********************************/

    public function getExName(){
        return $this->exchangeName;
    }

    /************************************
    /*************************************/


    /************************************
            ***FONCTION DE SCAN ****
     ************************************
     ***********************************/

    public function scanModeOn(){
        $this->scanMode = true;
    }

    public function scanModeOff(){
        $this->scanMode = false;
    }
    
    /*********************************************
    ********************************************/



   /************************************
            ***FONCTION D'EXECUTION ****
     ************************************
     ***********************************/

    //Fonction qui build la requête api
    public function apiQuery(string $type,string $url,array $data,array $header,bool $enableSigned){
        die('Fonction'.$this->exchangeName. '.apiQuery non configuré');

    }
    
    /**
     * Permet de récupérer à partir de l'orderbook le price d'une pair
     *
     * @param  mixed $orderList : Données d'orderbook
     * @return void
     */
    public function takePriceInfo($orderList = null){
        die('Fonction'.$this->exchangeName. '.takePriceInfo non configuré');

    }

    /**
     * Fonction pour qui envoie la requête de transfer d'un compte trade à principal ou inversement
     * @param mixed $type
     * @param mixed $coin
     * @param mixed $amount
     * @return void
     */
    public function innerTransfer($type,$coin,$amount){
        die($this->exchangeName . ".innerTransfer non config");
   
    }
    /**
     * Fonction qui orchestre la requête de transfert, utilise innerTransfer
     * @param mixed $type
     * @param mixed $coin
     * @param mixed $amount
     * @return void
     */
    public function launchTransfer($type, $coin)
    {
        die($this->exchangeName . ".launchTransfer non config");

    }
        
    /**
     * Renvoie un tableau qui donne le montant à la fois dans le compte trading et principal
     *
     * @param  mixed $coin
     * @return void
     */
    public function getCoinAmount4Transfer($coin)
    {
        die($this->exchangeName . ".launchTransfer non config");

    }
    
    /**
     * Fonction pour build la requête qui va chercher les données de l'orderbook
     *
     * @return void
     */
    public function orderBookQuery(){
        die('Fonction'.$this->exchangeName.'.orderBookQuery non configuré');
    }    
    /**
     * Fonction pour obtenir l'orderbook du type selectionnée bids ou asks
     *
     * @param  mixed $orderList : Est requis si le scanmode est activé (doit rester null sans scanmode)
     * @return void
     */
    public function getOrderBook($orderList = null){
        die('Fonction'.$this->exchangeName.'.getOrderBook non configuré');
    }


    /**
     * Avoir les informations sur une paire
     *
     * @return void
     */
    public function getExchangeInfo(){
        die('Fonction'.$this->exchangeName.'.getExchangeInfo non configuré');

    }

     
        
 
    /**
     * Vérifie si on peut trade une pair
     *
     * @param  mixed $coin
     * @return void
     */
    public function isTradingEnabled(){
        die('Fonction'.$this->exchangeName.'.isTradingEnabled non configuré');

    }   

    
    /**
     * Obtenir le nombre de coin que l'on dispose exemple avec la paire XRPUSDT
     * Si notre side est buy : On obtient le montant d'USDT que l'on dispose
     * Si notre side est sell: On obtient le montant d'XRP que l'on dispose
     * 
     * PS : Pour binance il n'y a jamais besoin du type, le type est utile que pour les exchanges qui dissocient portefeuille trading et principal comme :
     *   Kucoin & Okex 
     * @return void
     */
    public function getCoinAmount($type = null){
        die('Fonction'.$this->exchangeName.'.getCoinAmount non configuré');
    }

    
    /**
     * Récupère les informations sur une Coin (pour l'api de binance ça récupère pour toutes les coin puis on les traites individuellement)
     *
     * @param  mixed $coin
     * @return void
     */
    public function getCoinInfo($coin){
        die('Fonction'.$this->exchangeName.'.getCoininfo non configuré');

    }

     /**
     * Vérifie si on peut withdraw une coin
     *
     * @param  mixed $coin
     * @return void
     */
    public function isWdEnabled($coin){
        die('Fonction'.$this->exchangeName.'.isWdEnabled non configuré');

    }

    
    /**
     * Récupère le nombre de decimal que la crypto exige
     *
     * @param  mixed $coin
     * @return void
     */
    public function getCoinPrecision($coin){
        die('Fonction'.$this->exchangeName.'.getCoinPrecision non configuré');

    }
    
    
      
    /**
     * Récupérer les frais de trading
     * Le $amount est toujours celui de l'asset de base pour la paire XRP-USDT ce sera donc le montant de XRP
     * @return void
     */
    public function getTradeFee($amount){
        die('Fonction'.$this->exchangeName.'.getTradeFee non configuré');

    }

    /**
     * Avoir le stepsize pour éviter les erreurs de lots lors d'un buy market
     *
     * @return void
     */
    public function getStepSize($value){
        die('Fonction'.$this->exchangeName.'.getStepSize non configuré');

    }
    /**
     * Permet de faire un order 
     * Le side peut etre change avec sideToSeller() ou sideToBuyer() en conséquence la quantité calculée changera et coinAmount aussi
     * @return void
     */
    public function convertCoin(){
        die('Fonction'.$this->exchangeName.'.convertCoin non configuré');
    }


    //Check if a conversion coin-stablecoin or stablecoin-coin is finished (aussi appelé order par exemple sur binance)
    public function checkConvertStatus(){
        die('Fonction'.$this->exchangeName.'.checkConvertStatus non configuré');
    }

       /**
     * Récupère les frais de withdraw
     *
     * @param  mixed $coin
     * @return void
     */
    public function getWithdrawFee($coin){
        die('Fonction'.$this->exchangeName.'.getWithdrawFee non configuré');

    }    


    // Envoyer le coin vers un autre exchange
    public function withdrawCoin($coin,$amount,$address,$tag=null){
        die('Fonction'.$this->exchangeName.'.withdrawCoin non configuré');
    }
    


    //Check if a withdraw is finished
    public function checkWithdrawStatus(){
        die('Fonction'.$this->exchangeName.'.checkWithdrawStatus non configuré');

    }

   
 
    public function sideToBuy(){
        $this->side = "buy";
    }

    
    
    public function sideToSell(){
        $this->side = "sell";
    }



     /**
     * Récupère le réseau associé à une crypto (voir config.php)
     *
     * @param  mixed $coin
     * @return void
     */
    public function getNetwork($coin){
        $network = $this->chainNetwork[strtoupper($coin)];
        
        if ($network == null)
            return false;
        else
            return $network;
    }

    
    public function calcCoin2StableCoin($amount){
        return $amount * $this->priceStableCoin;
    }

    
    public function calcStableCoin2Coin($amount){
        return $amount / $this->priceStableCoin;
    }
    public function calculateStepSize($value,$stepSize){
            $precision = strlen(substr(strrchr(rtrim($value,'0'), '.'), 1));
            return round((($value / $stepSize) | 0) * $stepSize, $precision);
    }
    //  false si c'est blacklisté
    public function isCoinNotBlacklisted(){
            $blackList = __BLACKLIST_COIN__[$this->getExName()];
            $coin = in_array(strtoupper($this->coin),$blackList);
            $stableCoin = in_array(strtoupper($this->stableCoin),$blackList);
            return ($coin == true || $stableCoin == true) ? false : true;
    }

    // true si toutes les coin disposent bien d'un network, false pour le cas contraire
    public function haveANetwork(){
        $coin = $this->getNetwork(strtoupper($this->coin));
        $stableCoin = $this->getNetwork(strtoupper($this->stableCoin));
        return ($coin == false || $stableCoin == false) ? false : true;
    }

    public function canArbitrage(){
        $result = array(
            'coinWd' => $this->isWdEnabled($this->coin),
            'stableCoinWd' => $this->isWdEnabled($this->stableCoin),
            'trading' => $this->isTradingEnabled(),
            'blacklist' => $this->isCoinNotBlacklisted(),
            'network' => $this->haveANetwork(),
            'haveAddress' => $this->haveAddress()
        );

        foreach($result as $value){
                if ((bool)$value === false)
                    return false;
        }

        return true;
    }

    // false si il n'y a pas d'addresse (voir config.php)
    public function haveAddress(){
        $coin = $this->addressList[strtoupper($this->coin)]['address'];
        $stableCoin = $this->addressList[strtoupper($this->stableCoin)]['address'];

        if($coin == null || $stableCoin == null)
            return false;
        else
            return true;
    }
    /*********************************************
    ********************************************/

}