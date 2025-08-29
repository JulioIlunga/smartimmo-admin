<?php 

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;


Class MailerService

{
    private $fromMail;
    private $fromName;
    private $mailer;
    private $mailCopy;
    private $returnPath;

    public function __construct(string $fromMail, string $fromName, string $returnPath, string $mailCopy, MailerInterface $mailer)
    {
        $this->fromMail = $fromMail;
        $this->fromName = $fromName;
        $this->mailer = $mailer;
        $this->returnPath = $returnPath;
        $this->mailCopy = $mailCopy;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendTemplatedMail(string $to, string $subject, string $template, array $parameter = []): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromMail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($parameter)
        ;

        $this->mailer->send($email);
    }
}



