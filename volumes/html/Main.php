<?php

class Main {
    public $output = array("type" => "error", "message" => "Aucune action n'as été engagée");

    public $exchangeToThreat = array();
    
    public $coin; //exemple: BTC ou ADA 
    public $stableCoin; //exemple: USDT ou BUSD

    public $handler = array();
    public $highPrice;
    public $lowPrice;

    
    
    
    public function __construct(string $coin,string $stableCoin){
            $this->coin = strtolower($coin);
            $this->stableCoin = strtolower($stableCoin);
    }

    public function getAllExchangePrice(){
        global $db;
        $listAPI = getListAPI();
        $mh = curl_multi_init();
        $passToStep = false; // passer à la prochaine étape
        $counter = 0; // Compteur d'exchange avec le prix disponible si >= 2 On continue l'arbitrage sinon on arrêtera
        $coin = $this->coin;
        $stableCoin= $this->stableCoin;
        $this->resetHandler();
        echo $coin . "-" . $stableCoin;
        // On prépare les requêtes asynchrone pour recup le prix
        foreach($listAPI as $value){
            $this->handler[$value] = array();
            $this->handler[$value]['object'] = new $value($coin,$stableCoin);
            $this->handler[$value]['object']->sideToSell(); 
            $this->handler[$value]['query'] = $this->handler[$value]['object']->orderBookQuery(); 
            curl_multi_add_handle($mh, $this->handler[$value]['query']);
        }
            // On lance les requêtes
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);

            // On récup les objets des exchanges contenant un prix à partir des requêtes
            foreach($this->handler as $key => $value){
                    $this->handler[$key]['object']->scanModeOn();
                    $queryResult = json_decode(curl_multi_getcontent($this->handler[$key]['query']));
                    $isReqSuccess = $this->handler[$key]['object']->takePriceInfo($queryResult);
                    if($isReqSuccess){
                        $this->exchangeToThreat[$key] = $value;
                        $this->exchangeToThreat[$key]['orderList'] = $queryResult;
                        echo $key . "<br/>";
                        arrayPrint($queryResult);
                        $this->exchangeToThreat[$key]['query'] = "";
                        $counter++;
                    }
            }


        die();
            if($counter >= 2){
                $passToStep = true;
            }else{
                logDB("Main.getAllExchangePrice() : Il n'y a pas assez d'exchange disponible pour ". $this->coin."_".$this->stableCoin );
            }
            
            
            // On close le gestionnaire multirequete
            foreach($this->handler as $key => $value){
                curl_multi_remove_handle($mh, $this->handler[$key]['query']);

            }
            

            curl_multi_close($mh);

            return $passToStep;
    }


    // Extrait les informations sur le prix de la pair d'un exchange qui a assez de liquidité fixé par la constante __MIN_LIQUIDITE__
    public function extractInfoFromExchange($priceObj,$orderList){
        
        $info = array(
           'price_asks' => 0, //prix stableCoin
           'price_bids' => 0, //prix StableCoin
           'liq_asks' => 0, // liquidité vendeur
           'liq_bids' => 0 // liquidite acheteurs
        );
        $infoNull = $info;
        $priceObj->scanModeOn();
        $priceObj->sideToSell();
        $priceInfo = $priceObj->takePriceInfo($orderList);
        if($priceInfo == false)
            return false;            

        $info['price_asks'] = $priceObj->priceStableCoin;
        $info['liq_asks'] = $priceObj->liquidity;
        
        $priceObj->sideToBuy();
        $priceInfo = $priceObj->takePriceInfo($orderList);
        if($priceInfo == false)
             return false;
        $info['price_bids'] = $priceObj->priceStableCoin;
        $info['liq_bids'] = $priceObj->liquidity;

        return $info;
    }


    /**
     * Cette fonction donne l'exchange source ainsi que l'exchange de destination
     * Source : Celui dans lequel le prix des coin disponible en vente (asks) est le moins cher
     * Destination: Celui dans lequel le prix des coin disponible à l'achat (bids) est le plus cher
     * 
     * $listExchange : Tableau contenant les données de nos exchanges
     */
    public function getFromAndToExchange(array $listExchange){
            $low_asks = $listExchange[array_key_first($listExchange)]; 
            $high_bids = $listExchange[array_key_first($listExchange)];
                
            foreach($listExchange as $value){
                if ($value['infoPair']['price_asks'] <= $low_asks['infoPair']['price_asks'])
                    $low_asks = $value;

                if ($value['infoPair']['price_bids'] >= $high_bids['infoPair']['price_bids'])
                    $high_bids = $value;
            }

            //Si le low_asks et le high_bids ont le même exchange alors, on change l'exchange du high_bids par un différent  
            if($low_asks['object']->exchangeName == $high_bids['object']->exchangeName){
                $tmp = $high_bids['infoPair']['price_bids'];
                $excludeName = $high_bids['object']->exchangeName;
                $high_bids['infoPair']['price_bids'] = 0;
                foreach($listExchange as $value){
                    if ($value['infoPair']['price_bids'] >= $high_bids['infoPair']['price_bids'] && $value['object']->exchangeName != $excludeName)
                        $high_bids = $value;
                }

                $high_bids['infoPair']['price_bids'] = $tmp;
            }
           
            return array(
                'from' => $low_asks,
                'to' => $high_bids
            );
    }
    
    public function removeExchangeWithoutPriceInfo(array $listExchange){
        foreach($listExchange as $key => $value){
            if ($listExchange[$key]['infoPair'] === false)
                unset($listExchange[$key]);
        }

        return $listExchange;
    }
    public function scanArbitrage(){
        global $db;

        $coin = $this->coin;
        $stableCoin = $this->stableCoin;
 
        foreach($this->exchangeToThreat as $key => $value)
            $this->exchangeToThreat[$key]['infoPair'] = $this->extractInfoFromExchange($value['object'],$value['orderList']);

        //L'infoPair false survient lorsque les critères de liquidités ne sont pas remplis
        $this->exchangeToThreat = $this->removeExchangeWithoutPriceInfo($this->exchangeToThreat);
        $finalExchange = $this->getFromAndToExchange($this->exchangeToThreat);


        
        // On check si le prix de vente d'une coin sur l'exchange le plus haut et supérieur ou égale à X pourcent
        // ET le prix disponible des acheteurs d'une crypto dans l'exchange High doit être supérieur à celui de l'exchange low
        // PS : voir avec si l'achat doit aussi être égal à X pourcent 
        
        $exFrom = $finalExchange['from'];
        $exTo = $finalExchange['to'];

        // On calcul l'écart de prix entre les deux exchange entre le prix des acheteurs (bids) et le prix des vendeurs (asks) 
        $ecartPercent = $this->ecartPrixPourcent($exTo['infoPair']['price_bids'],$exFrom['infoPair']['price_asks']);
        $ecartPrix = $this->ecartPrix($exTo['infoPair']['price_bids'],$exFrom['infoPair']['price_asks']);
        $echoStat = "\n                                         Ecart prix  : " . $ecartPercent . "%\n                                         Liquidité exchangeFrom vente(asks) : " . $exFrom['infoPair']['liq_asks'] . "\n                                         Liquidité exchangeTo achat(bids) : " . $exTo['infoPair']['liq_bids']."\n                                         Prix from Asks : " . $exFrom['infoPair']['price_asks'] ."\n                                         Prix To Bids : " . $exTo['infoPair']['price_bids'].PHP_EOL;

    //        echo $exFrom['infoPair']['price_asks'] . " | " . $exTo['infoPair']['price_bids'].PHP_EOL;

        if($ecartPercent >= __ARB_PERCENTAGE__){
                

          
            $checkArb = checkIfArbitrageExist($coin,$stableCoin,$exFrom['object']->getExName(),$exTo['object']->getExName(),$ecartPercent);


//            echo "\e[32m"."[SUCCESS] : (From)".$exFrom['object']->getExName()." - (To)".$exFrom['object']->getExName(). " [$coin - $stableCoin] : ".$echoStat."\e[0m".PHP_EOL;

            if(!$checkArb){
                
            
                    insertArbitrageResultDB(1,$coin,$stableCoin,$exFrom['object']->getExName(),$exTo['object']->getExName(),$ecartPrix,$ecartPercent);
       
            }else{
                
                    $liquidity = array(
                        'liqFrom_asks' => $exFrom['infoPair']['liq_asks'],
                        'liqFrom_bids' => $exFrom['infoPair']['liq_bids'],
                        'liqTo_asks' =>  $exTo['infoPair']['liq_asks'],
                        'liqTo_bids' => $exTo['infoPair']['liq_bids']
                    );
                
             
                updateArbitrageResultDB($checkArb['id'],$checkArb['heure'],$ecartPrix,$ecartPercent,$liquidity);

            }

        }else{
            echo "\e[31m"."[FAILED] : (From)".$exFrom['object']->getExName()." - (To)".$exTo['object']->getExName(). " [$coin - $stableCoin] : ".$echoStat."\e[0m".PHP_EOL;
            insertArbitrageResultDB(0,$coin,$stableCoin,$exFrom['object']->getExName(),$exTo['object']->getExName(),$ecartPrix,$ecartPercent);
        }
        
    }


    public function resetHandler(){
            $this->handler = array();
    }
    

    
    public function ecartPrixPourcent($highPrice,$lowPrice){
                if ($lowPrice == 0)
                        return 0;
                return (( $highPrice - $lowPrice ) / $lowPrice ) * 100; 
    }

    public function ecartPrix($highPrice,$lowPrice){
        return $highPrice - $lowPrice;
    }


    // Fonction qui nous indique ou se trouve notre stablecoin en appelant le amount de chaques exchanges
    public function whereIsStableCoin(){

    }

    //Fonction pour savoir si un arbitrage est toujours fonctionnel
    public function isStillArbitrage(){
    }
}