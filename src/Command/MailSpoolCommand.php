<?php

namespace Bolt\Extension\Bolt\EmailSpooler\Command;

use Bolt\Filesystem\Exception\IOException;
use Bolt\Filesystem\Handler\DirectoryInterface;
use Bolt\Filesystem\Handler\FileInterface;
use Bolt\Nut\BaseCommand;
use Carbon\Carbon;
use Swift_FileSpool as SwiftFileSpool;
use Swift_Mailer as SwiftMailer;
use Swift_Message as SwiftMessage;
use Swift_Transport_SpoolTransport as SwiftTransportSpoolTransport;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MailQueueCommand
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class MailSpoolCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('email:spool')
            ->setDescription('Manage the email spool.')
            ->addOption('clear', null,   InputOption::VALUE_NONE, 'Clear all un-sent message files from the queue. USE WITH CAUTION!')
            ->addOption('flush', null,   InputOption::VALUE_NONE, 'Flush (send) any queued emails.')
            ->addOption('recover', null, InputOption::VALUE_NONE, 'Attempt to restore any incomplete email to a valid state.')
            ->addOption('show', null,    InputOption::VALUE_NONE, 'Show any queued emails.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->app['swiftmailer.use_spool']) {
            $output->write('<info>Spool is disabled</info>');

            return;
        }

        /** @var SwiftMailer $mailer */
        $mailer = $this->app['mailer'];
        /** @var SwiftTransportSpoolTransport $transport */
        $transport = $mailer->getTransport();
        /** @var SwiftFileSpool $spool */
        $spool = $transport->getSpool();

        if ($input->getOption('show')) {
            $this->showQueue($output);
        } elseif ($input->getOption('recover')) {
            $output->write('<info>Attempting recovery of failed email messages to the queue…</info>');
            $spool->recover();
            $output->writeln('<info>  [OK]</info>');
        } elseif ($input->getOption('flush')) {
            $output->write('<info>Flushing queued emails…</info>');
            $spool->flushQueue($this->app['swiftmailer.transport']);
            $output->writeln('<info>  [OK]</info>');
        } elseif ($input->getOption('clear')) {
            $output->writeln('<info>Deleting un-sent emails from the queue…</info>');
            $this->clearQueue($output);
        } else {
            $output->writeln('<info>no option found ... please use one of "--clear|flush|recover|show"</info>');
        }
    }

    /**
     * Delete any unsent messages from the queue.
     *
     * @param OutputInterface $output
     */
    protected function clearQueue(OutputInterface $output)
    {
        $failed = 0;
        $messages = 0;
        $spoolCache = $this->app['filesystem']->getFilesystem('cache');
        foreach ($spoolCache->listContents('.spool', false) as $item) {
            if ($item instanceof DirectoryInterface) {
                continue;
            }
            /** @var FileInterface $item */
            if ($item->getExtension() === 'message') {
                try {
                    $item->delete();
                    $messages++;
                } catch (IOException $e) {
                    $failed++;
                }
            }
        }
        $table = new Table($output);
        $table->addRows([
            ['Deleted', $messages],
            ['Failed', $failed],
        ]);
        $table->render();
    }

    /**
     * Show a table of queued messages.
     *
     * @param OutputInterface $output
     */
    protected function showQueue(OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>Currently queued emails:</info>');

        $table = new Table($output);
        $table->setHeaders(['', 'Date', 'Address', 'Subject']);

        $i = 1;
        $spoolCache = $this->app['filesystem']->getFilesystem('cache');
        foreach ($spoolCache->listContents('.spool', false) as $item) {
            if ($item instanceof DirectoryInterface) {
                continue;
            }
            /** @var FileInterface $item */
            if ($item->getExtension() !== 'message') {
                continue;
            }

            /** @var SwiftMessage $message */
            $message = unserialize($item->readStream());
            if ($message) {
                $to = $message->getTo();
                $subject = $message->getSubject();
                $date = Carbon::createFromTimestamp($message->getDate())->format('c');

                $table->addRow([$i++, $date, sprintf('%s <%s>', current($to), key($to)), $subject]);
            }
        }
        $table->render();
    }
}
