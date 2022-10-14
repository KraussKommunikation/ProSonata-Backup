<?php

class ProSonata
{

    private $workspaceURL = null;
    private $appID = "";
    private $apiKey = "";

    const GET = "GET";
    const POST = "POST";
    const PUT = "PUT";
    const DELETE = "DELETE";

    function __construct( $workspaceURL, $appID, $apiKey )
    {
        $this->workspaceURL = $workspaceURL;
        $this->appID = $appID;
        $this->apiKey = $apiKey;
    }

    function request( $ressource, $method = self::GET, $data = [] )
    {
        if(
            $method !== self::GET
            && $method !== self::POST
            && $method !== self::PUT
            && $method !== self::DELETE
        ) {
            return throw new Exception("Invalid request method: '" + $method + "'");
        }

        $url = $this->workspaceURL . "/api/v1/" . $ressource;

        $getParams = ($data["search"] ?? []);
        $url .= "?" . http_build_query($getParams);

        $headers = array(
            "Content-Type: application/json",
            "X-APP-ID: " . $this->appID,
            "X-API-Key: " . $this->apiKey,
            ...$data["headers"] ?? [],
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if( isset($data["body"]) && is_array($data["body"]) )
        {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data["body"]));
        }

        $curl_result = curl_exec($curl);

        if(curl_errno($curl)) {
            curl_close($curl);
            return null;
        }

        $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($curl_result, 0, $header_size);
        $body = substr($curl_result, $header_size);

        curl_close($curl);

        return (object) array(
            "status" => $status_code,
            "header" => $header,
            "body" => $body,
        );
    }

}
