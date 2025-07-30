<?php

// src/Service/InvoiceService.php
namespace App\Service;

use Stripe\Checkout\Session;
use Stripe\Invoice;

class InvoiceService
{
    public function getInvoicePdfFromSessionId(string $sessionId): string
    {
        $session = Session::retrieve($sessionId);

        if (!$session->customer) {
            throw new \Exception('Aucun client lié à cette session.');
        }

        $invoices = Invoice::all([
            'customer' => $session->customer,
            'limit' => 1,
        ]);

        $invoice = $invoices->data[0] ?? null;

        if (!$invoice || !$invoice->invoice_pdf) {
            throw new \Exception('Aucune facture disponible.');
        }

        return $invoice->invoice_pdf;
    }


    public function getCustomerEmailFromSessionId(string $sessionId): ?string
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        return $session->customer_details->email ?? null;
    }

}


