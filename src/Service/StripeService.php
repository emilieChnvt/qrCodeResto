<?php
// src/Service/StripeService.php
namespace App\Service;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Checkout\Session as CheckoutSession;

class StripeService
{
    public function __construct(string $stripeSecretKey)
    {
        Stripe::setApiKey($stripeSecretKey);
    }

    public function createCheckoutSession(string $lookupKey, string $successUrl, string $cancelUrl): CheckoutSession
    {


        $prices = Price::all([
            'lookup_keys' => [$lookupKey],
            'expand' => ['data.product'],
        ]);

        if (empty($prices->data)) {
            throw new \Exception("Aucun prix trouvé pour la clé : $lookupKey");
        }

        $priceId = $prices->data[0]->id;


        return CheckoutSession::create([
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    }
