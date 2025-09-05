<?php
namespace App\Helpers;
 
use Illuminate\Http\Request;

class Paytm {

    public static function encrypt($data, $key, $iv)
    {
        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        $ciphertext_raw = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        return bin2hex($ciphertext_raw);
    }

    public static function decrypt($data, $key, $iv)
    {
        $data = hex2bin($data);
        return json_decode(openssl_decrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv));
    }
}
