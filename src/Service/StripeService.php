<?php
// src/Service/StripeService.php
namespace App\Service;

use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Checkout\Session as CheckoutSession;

class StripeService
{
    public function __construct(string $stripeSecretKey)
    {
        Stripe::setApiKey($stripeSecretKey);
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(string $lookupKey, string $successUrl, string $cancelUrl, string $customerId): CheckoutSession
    {
        $priceMap = [
            'key' => 'price_1Rsmzz06EEhfyUPZbT1xXhdz',
        ];

        $priceId = $priceMap[$lookupKey] ?? null;
        if (!$priceId) {
            throw new \Exception("Lookup key non trouvé");
        }

        return \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'customer' => $customerId,  // <- ajoute ici
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    // src/Service/StripeService.php

    /**
     * @throws ApiErrorException
     */
    public function createCustomer(string $email): \Stripe\Customer
    {
        return \Stripe\Customer::create([
            'email' => $email,
        ]);
    }



}
