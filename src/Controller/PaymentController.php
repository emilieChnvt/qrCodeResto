<?php

namespace App\Controller;

use App\Service\InvoiceService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Mailgun\Mailgun;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{

    private $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    #[Route('/payment', name: 'payment_index')]
    public function payment(): Response
    {
        if(!$this->getUser()){
            return $this->redirectToRoute('app_login');
        }

        $priceLookupKey = 'key';

        return $this->render('payment/index.html.twig', [
            'PRICE_LOOKUP_KEY' => $priceLookupKey,
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'], // ou depuis config/services.yaml
        ]);
    }


    /**
     * @throws ApiErrorException
     */
    #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Request $request, UrlGeneratorInterface $urlGenerator, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            throw new \Exception('Utilisateur non connecté');
        }

        // Vérifie si l'utilisateur a déjà un Stripe Customer ID
        if (!$user->getStripeCustomerId()) {
            $customer = $this->stripeService->createCustomer($user->getEmail());
            $user->setStripeCustomerId($customer->id);
            $em->flush();
        }

        $lookupKey = $request->request->get('lookup_key', 'key');

        $successUrl = $urlGenerator->generate('payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
            . '?session_id={CHECKOUT_SESSION_ID}';

        $cancelUrl = $urlGenerator->generate('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Passe le stripeCustomerId dans la création de session
        $checkoutSession = $this->stripeService->createCheckoutSession(
            $lookupKey,
            $successUrl,
            $cancelUrl,
            $user->getStripeCustomerId()  // <- ici
        );


        return new JsonResponse(['id' => $checkoutSession->id]);
    }


    #[Route('/payment/success', name: 'payment_success')]
    public function success(
        Request $request,
        InvoiceService $invoiceService
    ): Response {
        $sessionId = $request->get('session_id');

        $pdfUrl = $invoiceService->getInvoicePdfFromSessionId($sessionId);
        $customerEmail = $invoiceService->getCustomerEmailFromSessionId($sessionId);

        if (!$pdfUrl || !$customerEmail) {
            throw new \Exception("Erreur : facture ou email client manquant.");
        }


        // 1. Télécharger le PDF localement

        $tempFile = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';
        file_put_contents($tempFile, file_get_contents($pdfUrl));
        // Envoi de l'email avec Mailgun
        $mg = Mailgun::create($_ENV['MAILGUN_API_KEY'], 'https://api.eu.mailgun.net');

        $mg->messages()->send('mg.emiliechanavat.com', [
            'from'    => 'Emilie Chanavat <postmaster@mg.emiliechanavat.com>',
            'to'      => $customerEmail,
            'subject' => 'Votre facture - Emilie Chanavat',
            'html' => $this->renderView('emails/payment_invoice.html.twig', [
                'pdf_url' => $pdfUrl,
            ]),
            'attachment' => [
                ['filePath' => $tempFile, 'filename' => 'facture_qrmenu.pdf']
            ],

        ]);
        // 3. Supprimer le fichier temporaire
        unlink($tempFile);


        return $this->render('payment/success.html.twig', [
            'pdf_url' => $pdfUrl,
        ]);
    }
    #[Route('/payment/cancel', name: 'payment_cancel')]
    public function cancel()
    {
        return $this->render('payment/cancel.html.twig');
    }


    #[Route('/billing-portal', name: 'billing_portal')]
    public function billingPortal(Request $request, UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        $user = $this->getUser();

        if (!$user || !$user->getStripeCustomerId()) {
            throw $this->createAccessDeniedException('Utilisateur non connecté ou sans client Stripe');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $session = BillingPortalSession::create([
            'customer' => $user->getStripeCustomerId(),
            'return_url' => $urlGenerator->generate('account_index', [], UrlGeneratorInterface::ABSOLUTE_URL), // redirige où tu veux après
        ]);

        return $this->redirect($session->url);
    }

    #[Route('/account', name: 'account_index')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig');
    }
}
