<?php

// src/Command/CreateUserCommand.php

namespace Conduction\CommonGroundBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

class DocumentationCommand extends Command
{
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:documentation:generate')
            // the short description shown while running "php bin/console list"
            ->setDescription('Generates documentation files in app folder.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $version */

        $readMe = $this->twig->render('@CommonGround/repo/README.md.twig');
        file_put_contents('README.MD', $readMe);
        $io->success(sprintf('Data written to %s/README.MD.', '/app'));

        $env = $this->twig->render('@CommonGround/repo/env.env.twig');
        file_put_contents('.env', $env);
        $io->success(sprintf('Data written to %s/.env', '/app'));


        return 0;
    }
}
