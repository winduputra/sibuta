<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

class FcmService
{
  public static function getFCMAuthorizationToken($path = "")
  {
    if( !$path
      OR !file_exists($path) ) {
      throw new Exception("File .json tidak ditemukan");
    }

    $client   = new Client([
      'base_uri' => 'https://oauth2.googleapis.com'
    ]);

    $now  = Carbon::now();
    $anHour   = Carbon::now()->addMinutes(59);

    $iat  = strtotime($now->toDateTimeString());
    $exp  = strtotime($anHour->toDateTimeString());

    $file   = fopen($path, "r");
    $string = fread($file,filesize($path));
    fclose($file);
    $json = json_decode($string);

    $jwsHeader  = [
      'alg' => 'RS256',
      'typ' => 'JWT',
      'kid' => $json->private_key_id,
    ];

    $assertion = base64_encode(json_encode($jwsHeader)) . ".";

    $claimSet   = [
      'iss' => $json->client_email,
      'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
      'aud' => $json->token_uri,
      'iat' => $iat,
      'exp' => $exp,
    ];

    $assertion .= base64_encode(json_encode($claimSet)) . ".";

    $assertion  = JWT::encode($claimSet, $json->private_key, 'RS256', $json->private_key_id);

    try {
      $response   = $client->request('POST', 'token', [
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'form_params' => [
          'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
          'assertion' => $assertion,
        ]
      ]);
    } catch (BadResponseException $e) {
      throw $e;
    }
    
    $body   = $response->getBody()->getContents();
    $responseCode   = $response->getStatusCode();
    if( $responseCode != 200 ) {
      throw new Exception("Get token not success");
    }

    $json   = json_decode($body);

    return $json->access_token ?? null;
  }

  public static function sendNotification($token, $payloads)
  {
    $client   = new Client([
      'base_uri' => 'https://fcm.googleapis.com'
    ]);
    try {
      $response   = $client->request('POST', '/v1/projects/blog-dc96d/messages:send', [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $token,
        ],
        'json' => $payloads
      ]);
    } catch(BadResponseException $e) {
      throw $e;
    } catch (Exception $e) {
      throw $e;
    }
    $body   = $response->getBody()->getContents();
    $statusCode   = $response->getStatusCode();
    if( $statusCode > 299 OR $statusCode < 200 ) {
      throw new Exception("Response bukan 200");
    }
    return $body;
  }
}
