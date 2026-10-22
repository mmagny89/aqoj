<?php

namespace App\Tests\Functional;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JobAlertTest extends KernelTestCase
{
    public function testUneErreurDuCanalJobsPartParEmail(): void
    {
        self::bootKernel();
        /** @var LoggerInterface $logger */
        $logger = self::getContainer()->get('monolog.logger.jobs');

        $logger->error('Enrichissement interrompu : BGG injoignable');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'to', 'alerte@aqoj.test');
        self::assertEmailHtmlBodyContains($email, 'BGG injoignable');
    }

    public function testUnSimpleAvertissementNeDeclenchePasDAlerte(): void
    {
        self::bootKernel();
        /** @var LoggerInterface $logger */
        $logger = self::getContainer()->get('monolog.logger.jobs');

        $logger->warning('Une autre tâche BGG est déjà en cours');

        self::assertEmailCount(0);
    }
}
