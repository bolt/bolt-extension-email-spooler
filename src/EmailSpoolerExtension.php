<?php

namespace Bolt\Extension\Bolt\EmailSpooler;

use Bolt\Extension\SimpleExtension;
use Pimple as Container;
use Silex\Application;
use Swift_FileSpool as SwiftFileSpool;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        $app['swiftmailer.spool'] = $app->share(function ($app) {
            $spoolDir = $app['path_resolver']->resolve('%cache%/.spool');

            return new SwiftFileSpool($spoolDir);
        });

        $app['mailer.queue.listener'] = $app->share(
            function ($app) {
                return new EventListener\QueueListener($app);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        parent::boot($app);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $app['dispatcher'];
        $dispatcher->addSubscriber($app['mailer.queue.listener']);
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
