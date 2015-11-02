<?php

namespace Elgentos\Magento\Command\Dev\Events;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListenCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('dev:events:listen')
            ->setDescription('Listen to events being dispatched live')
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        declare(ticks = 1);

        $this->output = $output;

        pcntl_signal(SIGTERM, [$this, 'stopCommand']);
        pcntl_signal(SIGINT, [$this, 'stopCommand']);

        $this->detectMagento($output);
        if ($this->initMagento()) {
            $currentMagerunDir = dirname(__FILE__);
            $patch = $currentMagerunDir . '/0001-Added-logging-of-events.patch';
            // Enable logging & apply patch
            shell_exec('cd ' . \Mage::getBaseDir() . ' && n98-magerun.phar dev:log --on --global && patch -p1 < ' . $patch);
            $output->writeln('Tailing events... ');
            // Listen to log file
            shell_exec('echo "" > ' . \Mage::getBaseDir() . '/var/log/n98-magerun-events.log');
            $handle = popen('tail -f ' . \Mage::getBaseDir() . '/var/log/n98-magerun-events.log 2>&1', 'r');
            while(!feof($handle)) {
                $buffer = fgets($handle);
                $output->write($buffer);
                flush();
            }
            pclose($handle);
        }
    }

    public function stopCommand()
    {
        $currentMagerunDir = dirname(__FILE__);
        $revertPatch = $currentMagerunDir . '/0001-Revert-Added-logging-of-events.patch';
        // Revert patch
        shell_exec('cd ' . \Mage::getBaseDir() . ' && patch -p1 < ' . $revertPatch);
        if(file_exists(\Mage::getBaseDir() . '/app/Mage.php.rej')) {
            unlink(\Mage::getBaseDir() . '/app/Mage.php.rej');
        }
        $this->output->writeln(PHP_EOL . 'Press CTRL-C again to exit.');
    }
}