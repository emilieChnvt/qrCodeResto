<?php
// src/Command/CheckSubscriptionsCommand.php
namespace App\Command;

use AllowDynamicProperties;
use App\Repository\UserRepository;
use App\Service\MailgunService;
use Doctrine\ORM\EntityManagerInterface;
use Mailgun\Mailgun;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AllowDynamicProperties] class CheckSubscriptionsCommand extends Command
{
    protected static $defaultName = 'app:check-subscriptions';

    private $userRepository;
    private $em;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em, MailgunService $mailgunService)
    {
        parent::__construct();

        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->mailgunService = $mailgunService;

    }

    protected function configure()
    {
        $this->setName('app:check-subscriptions');
        $this->setDescription('Check expired subscriptions and downgrade users to free.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredUsers = $this->userRepository->findUsersWithExpiredSubscription();

        foreach ($expiredUsers as $user) {
            $user->setSubscriptionPlan('free');
            $user->setSubscriptionEndsAt(null);
            $this->em->flush();

            $this->mailgunService->send(
                $user->getEmail(),
                'Votre abonnement est terminé',
                "Bonjour,\n\nVotre abonnement pro est arrivé à échéance et vous avez été automatiquement basculé vers le plan gratuit.\n\nMerci de votre confiance."
            );

            $output->writeln('Downgraded user '.$user->getEmail().' to free plan and sent email.');
        }

        $output->writeln('Done checking subscriptions.');

        return Command::SUCCESS;
    }

}
