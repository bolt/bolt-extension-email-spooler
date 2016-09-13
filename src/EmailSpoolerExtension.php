<?php

namespace Bolt\Extension\Bolt\EmailSpooler;

use Bolt\Extension\Bolt\EmailSpooler\Command;
use Bolt\Extension\Bolt\EmailSpooler\EventListener\QueueListener;
use Bolt\Extension\SimpleExtension;
use Pimple as Container;
use Silex\Application;
use Swift_FileSpool as SwiftFileSpool;
use Swift_Mailer as SwiftMailer;
use Swift_Transport_SpoolTransport as SwiftTransportSpoolTransport;

/**
 * Email spooler extension loader.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class EmailSpoolerExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['mailer'] = $app->share(
            function ($app) {
                $app['mailer.initialized'] = true;
                $spoolDir = $app['resources']->getPath('cache/.spool');
                $transport = new SwiftTransportSpoolTransport($app['swiftmailer.transport.eventdispatcher'], new SwiftFileSpool($spoolDir));

                return new SwiftMailer($transport);
            }
        );

        $app['mailer.queue.listener'] = $app->share(
            function ($app) {
                return new EventListener\QueueListener($app);
            }
        );

        $app['dispatcher']->addSubscriber($app['mailer.queue.listener']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerNutCommands(Container $container)
    {
        return [
            new Command\MailSpoolCommand($container),
        ];
    }
}
