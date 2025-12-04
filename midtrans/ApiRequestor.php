<?php
namespace Midtrans;

use Exception;

class ApiRequestor
{
    public static function post($url, $server_key, $data_hash)
    {
        return self::remoteCall($url, $server_key, $data_hash, "POST");
    }

    public static function remoteCall($url, $server_key, $data_hash, $method)
    {
        $ch = curl_init();

        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($server_key . ':')
            ),
            CURLOPT_RETURNTRANSFER => 1,
            // CURLOPT_CAINFO => dirname(__FILE__) . "/data/cacert.pem"
        );

        // merging with Config::$curlOptions
        if (count(Config::$curlOptions)) {
            // We need to combine headers manually, because it's array and it will no be merged
            if (Config::$curlOptions[CURLOPT_HTTPHEADER]) {
                $mergedHeders = array_merge($curl_options[CURLOPT_HTTPHEADER], Config::$curlOptions[CURLOPT_HTTPHEADER]);
                $headerOptions = array( CURLOPT_HTTPHEADER => $mergedHeders );
            } else {
                $mergedHeders = $curl_options[CURLOPT_HTTPHEADER];
            }

            $curl_options = array_replace_recursive($curl_options, Config::$curlOptions, $headerOptions);
        }

        if ($method == "POST") {
            $curl_options[CURLOPT_POST] = 1;
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($data_hash);
        }

        curl_setopt_array($ch, $curl_options);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // curl_close($ch);

        if ($result === false) {
            throw new Exception('CURL Error: ' . curl_error($ch), curl_errno($ch));
        } else {
            try {
                $result_array = json_decode($result);
            } catch (Exception $e) {
                throw new Exception("API Request Error unable to json_decode API response: ".$result . ' | Request url: '.$url);
            }

            if ($httpcode != 200 && $httpcode != 201 && $httpcode != 202) {
                $message = "Midtrans Error ({$result_array->status_code}): ".$result_array->status_message;
                if (isset($result_array->validation_messages)) {
                    $message .= ". Validation Messages: " . implode(', ', $result_array->validation_messages);
                }
                throw new Exception($message, $result_array->status_code);
            } else {
                return $result_array;
            }
        }
    }
}
?>