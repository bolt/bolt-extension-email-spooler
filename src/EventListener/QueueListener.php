<?php

namespace Bolt\Extension\Bolt\EmailSpooler\EventListener;

use Bolt\Filesystem\Filesystem;
use Bolt\Filesystem\Handler\File;
use Silex\Application;
use Swift_FileSpool as SwiftFileSpool;
use Swift_Mailer as SwiftMailer;
use Swift_Transport_SpoolTransport as SwiftTransportSpoolTransport;
use Swift_TransportException as SwiftTransportException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Email queue processing listener.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class QueueListener implements EventSubscriberInterface
{
    const RETRY = 'mailer.spool.retry';
    const FLUSH = 'mailer.spool.flush';

    /** @var Application */
    private $app;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle the processing of the SMTP queue.
     *
     * @param Event|null $event
     */
    public function flush(Event $event = null)
    {
        if (!$this->app['swiftmailer.use_spool']) {
            return;
        }

        /** @var SwiftMailer $mailer */
        $mailer = $this->app['mailer'];
        /** @var SwiftTransportSpoolTransport $transport */
        $transport = $mailer->getTransport();
        /** @var SwiftFileSpool $spool */
        $spool = $transport->getSpool();
        if ($event instanceof PostResponseEvent) {
            try {
                $spool->flushQueue($this->app['swiftmailer.transport']);
            } catch (SwiftTransportException $e) {
            }
        } else {
            $spool->flushQueue($this->app['swiftmailer.transport']);
        }
    }

    /**
     * Retry sending the contents of the SMTP queue.
     *
     * @return array
     */
    public function retry()
    {
        if (!$this->app['swiftmailer.use_spool']) {
            return;
        }

        $failedRecipients = [];
        /** @var SwiftMailer $mailer */
        $mailer = $this->app['mailer'];

        if ($this->app['cache']->contains('mailer.queue.timer')) {
            return $failedRecipients;
        }
        $this->app['cache']->save('mailer.queue.timer', true, 600);

        /** @var Filesystem $cacheFs */
        $cacheFs  = $this->app['filesystem']->getFilesystem('cache');
        if (!$cacheFs->has('.spool')) {
            return $failedRecipients;
        }

        $spooled = $cacheFs
            ->find()
            ->files()
            ->ignoreDotFiles(false)
            ->in('.spool')
            ->name('*.message')
        ;

        /** @var File $spool */
        foreach ($spooled as $spool) {
            // Unserialise the data
            $message = unserialize($spool->read());

            // Back up the file
            $spool->rename($spool->getPath() . '.processing');

            // Dispatch, again.
            $mailer->send($message, $failedRecipients);

            // Remove the file and retry
            $spool->delete();
        }

        return $failedRecipients;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::TERMINATE => 'retry',
            KernelEvents::TERMINATE => 'flush',
            self::RETRY             => 'retry',
            self::FLUSH             => 'flush',
        ];
    }
}
