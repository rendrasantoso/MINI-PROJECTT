<?php
namespace Midtrans;

class Midtrans
{
    /**
     * Your merchant's server key
     * @static
     */
    public static $serverKey;
    /**
     * Your merchant's client key
     * @static
     */
    public static $clientKey;
    /**
     * True for production
     * false for sandbox mode
     * @static
     */
    public static $isProduction = false;
    /**
     * Set it true to enable 3D Secure by default
     * @static
     */
    public static $is3ds = false;
    /**
     *  Set Append URL notification
     * @static
     */
    public static $appendNotifUrl = false;
    /**
     *  Set Override URL notification
     * @static
     */
    public static $overrideNotifUrl = false;
    /**
     *  Set Payment Idempotency Key
     * @static
     */
    public static $paymentIdempotencyKey = false;
    /**
     * Enable request params sanitizer (validate and modify charge request params).
     * See Midtrans_Sanitizer for more details
     * @static
     */
    public static $isSanitized = false;
    /**
     * Default options for every request
     * @static
     */
    public static $curlOptions = array();

    const SANDBOX_BASE_URL = 'https://api.sandbox.midtrans.com';
    const PRODUCTION_BASE_URL = 'https://api.midtrans.com';
    const SNAP_SANDBOX_BASE_URL = 'https://app.sandbox.midtrans.com/snap/v1';
    const SNAP_PRODUCTION_BASE_URL = 'https://app.midtrans.com/snap/v1';

    /**
     * @return string Midtrans API URL, depends on $isProduction
     */
    public static function getBaseUrl()
    {
        return Midtrans::$isProduction ?
            Midtrans::PRODUCTION_BASE_URL : Midtrans::SANDBOX_BASE_URL;
    }

    /**
     * @return string Snap API URL, depends on $isProduction
     */
    public static function getSnapBaseUrl()
    {
        return Midtrans::$isProduction ?
            Midtrans::SNAP_PRODUCTION_BASE_URL : Midtrans::SNAP_SANDBOX_BASE_URL;
    }
}
?>