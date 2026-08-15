<?php
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Creates a charge on Coinbase Commerce.
 *
 * @param float $amount The amount for the charge.
 * @param string $currency The currency for the charge (e.g., 'USD').
 * @param string $name The name of the item.
 * @param string $description A description of the item.
 * @return array|null The charge data or null on failure.
 */
function create_coinbase_charge($amount, $currency, $name, $description) {
    if (!defined('COINBASE_COMMERCE_API_KEY')) {
        // Log error or handle missing API key
        return null;
    }

    $client = new Client(['base_uri' => 'https://api.commerce.coinbase.com/']);

    try {
        $response = $client->post('charges', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-CC-Api-Key' => COINBASE_COMMERCE_API_KEY,
                'X-CC-Version' => '2018-03-22',
            ],
            'json' => [
                'name' => $name,
                'description' => $description,
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $amount,
                    'currency' => $currency,
                ],
                'redirect_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php',
                'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_cancelled.php',
            ],
        ]);

        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        // Log error or handle exception
        // error_log($e->getMessage());
        return null;
    }
}

/**
 * Verifies a Coinbase Commerce webhook signature.
 *
 * @param string $payload The raw request body.
 * @param string $signature The value of the 'X-CC-Webhook-Signature' header.
 * @return bool True if the signature is valid, false otherwise.
 */
function verify_coinbase_webhook($payload, $signature) {
    if (!defined('COINBASE_COMMERCE_WEBHOOK_SECRET')) {
        // Log error or handle missing webhook secret
        return false;
    }

    $secret = COINBASE_COMMERCE_WEBHOOK_SECRET;
    $computedSignature = hash_hmac('sha256', $payload, $secret);

    return hash_equals($signature, $computedSignature);
}
