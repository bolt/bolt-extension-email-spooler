<?php

namespace Bolt\Extension\Bolt\EmailSpooler\EventListener;

use Silex\Application;
use Swift_FileSpool as SwiftFileSpool;
use Swift_Mailer as SwiftMailer;
use Swift_Transport_SpoolTransport as SwiftTransportSpoolTransport;
use Swift_TransportException as SwiftTransportException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

/**
 * Email queue processing listener.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class QueueListener
{
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
}
