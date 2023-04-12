<?php
try
{
	$db = new PDO('mysql:host=mariadb;dbname=botjz;charset=utf8', 'root', $_SERVER['MYSQL_ROOT_PASSWORD']);
    
}   
catch (Exception $e)
{
        die('Erreur : ' . $e->getMessage());
}

function insertArbitrageResultDB($type,$coin1,$coin2,$exchangeFrom,$exchangeTo,$ecart_prix,$ecart_percent){
    global $db;
    $type = $type == 1 ? "arbitrage_scan" : "arbitrage_scan_failed";

    $req = $db->prepare('INSERT INTO '.$type.'(coin1,coin2,exchangeFrom,exchangeTo,ecart_prix,ecart_percent) VALUES(:coin1,:coin2,:exchangeFrom,:exchangeTo,:ecart_prix,:ecart_percent)');
    $req->execute(array(
        'coin1' => $coin1,
        'coin2' => $coin2,
        'exchangeFrom' => $exchangeFrom,
        'exchangeTo' => $exchangeTo,
        'ecart_prix' => $ecart_prix,
        'ecart_percent' => $ecart_percent
    ));
}


function calculate_median($arr) {
    $count = count($arr); //total numbers in array
    $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
    if($count % 2) { // odd number, middle is the median
        $median = $arr[$middleval];
    } else { // even number, calculate avg of 2 medians
        $low = $arr[$middleval];
        $high = $arr[$middleval+1];
        $median = (($low+$high)/2);
    }
    return $median;
}
function json_decode_print($json){
    $result = json_decode($json);
    if (json_last_error() === JSON_ERROR_NONE)
        return $result;
    else
        return print_r($json,true);
}
function pushLogJn($exchange,$type,$return,$functionName,$message,array $listVar,$httpResponse = null){
        $table = array(
            'date' => date('Y-m-d H:i:s'),
            'exchange' => $exchange,  
            'type' => null,
            'return' => null,
            'functionName'=> $functionName,
            'message' => $message,
            'listVar' => $listVar,
            'httpResponse' => $httpResponse
        );

        $GLOBALS['logJournal']['listExecution'][] = $table;
}

function arrayPrint($array){
    print("<pre>".print_r($array,true)."</pre>");
}



function logJournalToDB(){
    $log_journal = json_encode($GLOBALS['logJournal']);
    global $db;
    $req = $db->prepare('INSERT INTO log_journal(log) VALUES(:log)');
    $req->execute(array(
        'log' => $log_journal
    ));
    $GLOBALS['logJournal'] = array();
}

function updateArbitrageResultDB($id,$heure,$ecart_prix,$ecart_percent,array $liquidity){
    global $db;
    $heurePrec = new DateTime($heure);
    $heureActuelle = new DateTime();
    $interval = (int)($heureActuelle->diff($heurePrec)->format("%s"));


    $req = $db->prepare('UPDATE arbitrage_scan SET heure_last_arb = :heure , ecart_percent = :ecart_percent , ecart_prix = :ecart_prix , during = (during + :during),liqFrom_asks = :liqFrom_asks,liqFrom_bids = :liqFrom_bids,liqTo_asks = :liqTo_asks, liqTo_bids = :liqTo_bids WHERE id = :id');
    $req->execute(array(
        'heure' => $heureActuelle->format('Y-m-d H:i:s'),
        'ecart_percent' => $ecart_percent,
        'ecart_prix' => $ecart_prix,
        'id' => $id,
        'during' => $interval,
        'liqFrom_asks' => $liquidity['liqFrom_asks'],
        'liqFrom_bids' => $liquidity['liqFrom_bids'],
        'liqTo_asks' => $liquidity['liqTo_asks'],
        'liqTo_bids' => $liquidity['liqTo_bids']
    ));
}

function checkIfArbitrageExist($coin1,$coin2,$exchangeFrom,$exchangeTo,$ecart_percent){
    global $db;
  /*  $req = $db->prepare("SELECT * FROM arbitrage_scan WHERE
                        coin1 = :coin1 AND coin2 = :coin2 AND exchangeFrom = :exchangeFrom AND exchangeTo = :exchangeTo
                        AND (heure BETWEEN DATE_SUB(NOW(),INTERVAL 10 MINUTE) AND NOW())
                        AND IF(:ecart_percent >= (ecart_percent*0.90) AND :ecart_percent <= (ecart_percent*$ecPerc),1,0) = 1 LIMIT 1");
  */
  $req = $db->prepare("SELECT * FROM arbitrage_scan WHERE
  coin1 = :coin1 AND coin2 = :coin2 AND exchangeFrom = :exchangeFrom AND exchangeTo = :exchangeTo
  AND (heure BETWEEN DATE_SUB(NOW(),INTERVAL 5 MINUTE) AND NOW())
  AND ecart_percent >= ".__ARB_PERCENTAGE__." LIMIT 1");

$req->execute(array(
        'coin1' => $coin1,
        'coin2' => $coin2,
        'exchangeFrom' => $exchangeFrom,
        'exchangeTo' => $exchangeTo,
));
    
    
    if($req->rowCount() > 0)
        return $req->fetch(PDO::FETCH_ASSOC);
    else
        return false;
    
}

function getListAPI(){
    $listAPI = scandir(__DIR__."/api");
    $listAPIArray = array();
    foreach($listAPI as $value){
        if(is_file((__DIR__."/api/".$value)))
            array_push($listAPIArray,str_replace('.php','',$value));
    }

    return $listAPIArray;

}



//arrête le bot et envoie un message dans la db
function dieLogDB($message){
    
    logDB($message);
    die($message);
}
function logDB($message){
    global $db;
    $tz = new DateTimeZone('Europe/Paris');
    $date = new DateTime();
    $date->setTimeZone($tz);
    $req = $db->prepare('INSERT INTO log(message) VALUES(:message)');
    $req->execute(array(
        'message' => "[".$date->format('Y-m-d H:i:s')."] ".$message
    ));
}

