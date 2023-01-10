<?php

namespace App\Singlenton;

use GuzzleHttp\Client;
use Log;
use Carbon\Carbon;
use Config;

use App\Models\LtdSesion;

/**
* Singlenton para contriuir una peticion de creacion de guia y validacion del token
* 
* @param string $token
* @param string $baseUri
*  
*/

class Fedex {

    private static $instance;

    private $token;
    private $baseUri;

    public $documento = 0; 
    private $trackingNumber = 0;
    private $scanEvents = array();
    private $paquete = array();
    private $exiteSeguimiento = false;
    private $quienRecibio = "No entregado aun"; 

    private function __construct(int $ltd_id= 1, $empresa_id= 1, $plataforma = 'WEB'){

        Log::info(__CLASS__." ".__FUNCTION__);
        $this->baseUri = Config('ltd.fedex.base_uri');
        
        $sesion = LtdSesion::where('ltd_id', $ltd_id)
                ->where('expira_en','>', Carbon::now())
                ->first();

        if (!is_null($sesion)) {
            $this->token = $sesion->token;

        }else {
            Log::info(__CLASS__." ".__FUNCTION__." Seccion Else");

            if ($plataforma == 'WEB'){
                $empresa_id = auth()->user()->empresa_id;
            } 

            $client = new Client(['base_uri' => $this->baseUri]);

            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
                
            $body = sprintf("grant_type=client_credentials&client_id=%s&client_secret=%s"
                        ,Config('ltd.fedex.client_id'),Config('ltd.fedex.client_secret')
                    );
           
            $response = $client->request('POST', 'oauth/token', [
                    'headers'   => $headers
                    ,'body'     => $body
                ]);

            $contenido = json_decode($response->getBody()->getContents());

            Log::debug(print_r($contenido,true));

            $this->token = $contenido->access_token;

            $insert = array('empresa_id' => $empresa_id
                ,'ltd_id'   => $ltd_id
                ,'token'    => $this->token
                ,'expira_en'=> Carbon::now()->addHours(1)
                 );

            $id = LtdSesion::create($insert)->id;
            Log::info(__CLASS__." ".__FUNCTION__." ID LTD SESION $id");
        }
        
    }

    /**
     * Cliente busca crear una funcion donde se inicialice la peticion via guzzle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return GuzzleHttp\Client $response
     */

    private function clienteRest(array $body,$metodo = 'GET', $servicio){
        Log::debug(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        $client = new Client(['base_uri' => $this->baseUri]);
        $authorization = sprintf("Bearer %s",$this->token);

        $headers = ['Authorization' => $authorization
                    
                    ,'Content-Type' => 'application/json'
                    ,'charset' => 'utf-8'
                ];

        $bodyJson = json_encode($body);
        Log::debug(print_r($bodyJson,true));
        
        Log::debug(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
        return $client->request($metodo,$servicio , [
                    'headers'   => $headers
                    ,'body'     => $bodyJson
                ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function envio($body){

            $client = new Client(['base_uri' => $this->baseUri]);
            $authorization = sprintf("Bearer %s",$this->token);

            $headers = ['Authorization' => $authorization
                        ,'X-locale' => 'es_MX'
                        ,'Content-Type' => 'application/json'
                        ,'charset' => 'utf-8'
                    ];
            
            Log::debug(print_r($body,true));
            
            $response = $client->request('POST', 'ship/v1/shipments', [
                        'headers'   => $headers
                        ,'body'     => $body
                    ]);

            $contenido = json_decode($response->getBody()->getContents());
            Log::debug(print_r($contenido,true));
            $transactionShipments = $contenido->output->transactionShipments[0];

            $pieceResponses = $transactionShipments->pieceResponses;
       
            $documentos = "";
            $pieceResponsesCount = count($pieceResponses);
            foreach ($pieceResponses as $key => $value) {
                
                $documentos =sprintf("%s%s",$documentos, $value->packageDocuments[0]->url);

                Log::debug($pieceResponsesCount);
                Log::debug($key);

                if ($key < ($pieceResponsesCount-1)){
                    Log::debug("Entre");                    
                    $documentos =sprintf("%s|",$documentos);    
                }

            }

            $this->documento = $documentos;
            $this->trackingNumber = $transactionShipments->masterTrackingNumber;
            Log::debug(print_r($contenido->output,true));
       
    }


    /**
     * Rastreo busca los estatus con el LTD.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function rastreo(int $trackingNumber = 1){
        Log::debug(__CLASS__." ".__FUNCTION__." INICIANDO-----------------");
        $pesoDimension  = array('peso' => 0
                    , 'largo' => 0
                    , 'ancho' => 0
                    , 'alto' => 0
                );

        $body = array('trackingInfo' => [
                    array('trackingNumberInfo'=> array(
                        'trackingNumber'=> $trackingNumber)
                        )
                    ]
                    ,'includeDetailedScans' => true
                );
        
        $response = $this->clienteRest($body, 'POST','track/v1/trackingnumbers');

        Log::debug(__CLASS__." ".__FUNCTION__." response ");
        $contenido = json_decode($response->getBody()->getContents());
        foreach ($contenido->output->completeTrackResults as $key => $value) {
            foreach ($value->trackResults as $key1 => $value1) {
                //Log::debug(print_r($value1,true));

                if ( isset($value1->error)) {
                    Log::debug("No se econtro seguimiento");
                    continue;
                } else{
                    Log::debug("Seguimientos ");
                    Log::debug("scanEvents ".count($value1->scanEvents));
                    Log::debug(print_r($value1,true));
                    
                    $this->scanEvents = $value1->scanEvents[0];
                    foreach ($value1->packageDetails->weightAndDimensions->weight as $key => $value) {
                        if ($value->unit === 'KG') {
                            $pesoDimension['peso'] = $value->value;
                        }
                    }
                    
                    if (isset($value1->packageDetails->weightAndDimensions->dimensions) ) {
                        foreach ($value1->packageDetails->weightAndDimensions->dimensions as $key => $value2) {
                            if ( $value2->units=== 'CM' ){
                                $pesoDimension['largo'] = $value2->length;
                                $pesoDimension['ancho'] = $value2->width;
                                $pesoDimension['alto'] = $value2->height;
                            }

                        } 
                    }

                    if (isset($value1->deliveryDetails->receivedByName)) {
                        $this->quienRecibio = $value1->deliveryDetails->receivedByName;
                        
                    }
                    Log::debug(__CLASS__." ".__FUNCTION__." Asignando pesoDimension");
                    $this->paquete = $pesoDimension;
                    $this->exiteSeguimiento = true;
                } 
            }
        }
        
        Log::debug(__CLASS__." ".__FUNCTION__." FINALIZANDO-----------------");
       
    }

    public static function getInstance( int $ltd_id = 1,$empresa_id= 1, $plataforma = 'WEB'){
        if (!self::$instance) {
            Log::debug(__CLASS__." ".__FUNCTION__." Creando intancia");
            self::$instance = new self($ltd_id,$empresa_id, $plataforma);
        }
        Log::debug(__CLASS__." ".__FUNCTION__." return intancia");
        return self::$instance;
    }

    public function setToken($value){
        $this->token = $value;
    }

    public function getToken(){
        return $this->token;
    }

    public function getTrackingNumber(){
        return $this->trackingNumber;
    }

    public function getScanEvents(){
        return $this->scanEvents;
    }

    public function getPaquete(){
        return $this->paquete;
    }

    public function getExiteSeguimiento(){
        return $this->exiteSeguimiento;
    }

    public function getQuienRecibio(){
        return $this->quienRecibio;
    }
    
}

?>