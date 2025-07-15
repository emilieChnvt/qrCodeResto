<?php

namespace App\Controller;

use Mailgun\Mailgun;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class MailerController extends AbstractController
{
    #[Route('/mailer', name: 'app_mailer')]
    public function index(Request $request): Response
    {
        $mg = Mailgun::create($_ENV['MAILGUN_API_KEY'], 'https://api.eu.mailgun.net');
        $result = $mg->messages()->send(
            'mg.emiliechanavat.com',
            [
                'from'    => 'Emilie <postmaster@mg.emiliechanavat.com>',
                'to'      => 'emilie.chnvt@gmail.com',
                'subject' => 'Test Mailgun',
                'text'    => 'Ceci est un test.'
            ]
        );


        // Affiche un simple formulaire HTML pour envoyer un mail
        return new Response('
            <form method="post">
                <button type="submit">Envoyer un email</button>
            </form>
        ');
    }


}
