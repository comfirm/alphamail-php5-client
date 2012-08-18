<?php

    /*
    The MIT License

    Copyright (c) 2011 Comfirm <http://www.comfirm.se/>

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
    */
    
    // Include service contract
    include_once("emailservice.interface.php");
    
    // Include entities
    include_once("entities/serviceresponse.class.php");
    include_once("entities/emailcontact.class.php");
    
    // Include payloads
    include_once("entities/emailmessagepayload.class.php");
    include_once("entities/idempotentemailmessagepayload.class.php");
    
    // Include exceptions
    include_once("exceptions/alphamailserviceexception.class.php");
    include_once("exceptions/alphamailauthorizationexception.class.php");
    include_once("exceptions/alphamailinternalexception.class.php");
    include_once("exceptions/alphamailvalidationexception.class.php");
    
    // Include restful client
    include_once("comfirm.services.client.rest/restful.class.php");
    
    class AlphaMailEmailService implements IEmailService
    {
        private $_client = null;
        private $_service_url = null, $_api_token;
        
        protected function __construct()
        {
            $this->_client = new Restful();
        }
        
        public static function create()
        {
            return new AlphaMailEmailService();
        }
        
        public function setServiceUrl($service_url)
        {
            $this->_service_url = $service_url;
            return $this;
        }
        
        public function setApiToken($api_token)
        {
            $this->_api_token = $api_token;
            $this->_client->setBasicAuthentication(null, $api_token);
            return $this;
        }
        
        public function queueIdempotent(IdempotentEmailMessagePayload $payload)
        {
            throw new NotImplementedException("Not implemented. But on our todo! Contact our support for more information.");
        }
        
        public function queue(EmailMessagePayload $payload)
        {
            $response = null;
            
            try
            {
                $response = $this->_client->post($this->_service_url . "/email/queue", json_encode($payload));
                $response->result = $this->cast($response->result, "ServiceResponse");
                $this->handleErrors($response);
            }
            catch(AlphaMailServiceException $exception)
            {
                throw $exception;
            }
            catch(Exception $exception)
            {
                throw new AlphaMailServiceException($exception->getMessage(), null, null, $exception);
            }
            
            return $response->result;
        }
        
        private function handleErrors($response)
        {
            switch ($response->head->status->code)
            {
                // Successful requests
                case 202: // Accepted:
                case 201: // Created:
                case 200: // OK:
                    if ($response->result->error_code != 0){
                        throw new AlphaMailInternalException(
                            sprintf("Service returned success while response error code was set (%d)", $response->result->error_code),
                            $response->head->status,
                            $response->result,
                            null
                        );
                    }
                    break;

                // Unauthorized
                case 403: // Forbidden:
                case 401: // Unauthorized:
                    throw new AlphaMailAuthorizationException(
                        $response->result->message,
                        $response->head->status,
                        $response->result,
                        null
                    );

                // Validation error
                case 405: // MethodNotAllowed:
                case 400: // BadRequest:
                    throw new AlphaMailValidationException(
                        $response->result->message,
                        $response->head->status,
                        $response->result,
                        null
                    );

                // Internal error
                case 500: // InternalServerError
                    throw new AlphaMailInternalException(
                        $response->result->message,
                        $response->head->status,
                        $response->result,
                        null
                    );

                // Unknown
                default:
                    throw new AlphaMailServiceException(
                        $response->result->message,
                        $response->head->status,
                        $response->result,
                        null
                    );
            }

            return $response;
        }
        
        // Object-casting-hack :)
        private function cast($source, $destination)
        {
            $result = null;
            
            if(@class_exists($destination))
            {
                $serialized = @serialize($source);
                
                $result = @unserialize('O:' . strlen($destination) . ':"' . $destination . '":' .
                    substr($serialized, $serialized[2] + 7));
                
                if($result === false)
                    throw new Exception("Unable to cast object to type '" . $destination . "'");
            }
            else
            {
                return false;
            }
            
            return $result;
        }
    }
    
?>