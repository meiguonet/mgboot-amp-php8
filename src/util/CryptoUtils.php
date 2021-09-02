<?php

namespace mgboot\util;

final class CryptoUtils
{
    private function __construct()
    {
    }

    public static function aesEncrypt(
        string $text,
        string $key,
        string $iv,
        int $option = OPENSSL_RAW_DATA,
        ?string $mode = null
    ): string
    {
        if ($mode === null) {
            $n1 = 8 * strlen($key);
            $mode = "aes-$n1-cbc";
        }

        $result = openssl_encrypt($text, $mode, $key, $option, $iv);
        return is_string($result) ? $result : '';
    }

    public static function aesDecrypt(
        string $encryptedText,
        string $key,
        string $iv,
        int $option = OPENSSL_RAW_DATA,
        ?string $mode = null
    ): string
    {
        if ($mode === null) {
            $n1 = 8 * strlen($key);
            $mode = "aes-$n1-cbc";
        }

        $result = openssl_decrypt($encryptedText, $mode, $key, $option, $iv);
        return is_string($result) ? $result : '';
    }

    public static function pkcs7Pad(string $text, int $blockSize = 32): string
    {
        return $text . str_repeat(chr($blockSize), $blockSize);
    }

    public static function pkcs7Unpad(string $text, int $blockSize = 32): string
    {
        $ascii = ord(substr($text, -1));

        if ($ascii < 1 || $ascii > $blockSize) {
            $ascii = 0;
        }

        return substr($text, 0, (strlen($text) - $ascii));
    }

    public static function rsaPublicEncrypt(
        string $text,
        string $pemFilepath,
        int $paddingMode = OPENSSL_PKCS1_OAEP_PADDING
    ): string
    {
        $pemFilepath = FileUtils::getRealpath($pemFilepath);
        $pubkey = openssl_pkey_get_public("file://$pemFilepath");

        if ($pubkey === false) {
            return '';
        }

        $encrypted = '';
        openssl_public_encrypt($text, $encrypted, $pubkey, $paddingMode);

        if (empty($encrypted)) {
            return '';
        }

        return base64_encode($encrypted);
    }
}
