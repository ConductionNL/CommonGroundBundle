<?php

// src/Command/CreateUserCommand.php

namespace Conduction\CommonGroundBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
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

        $fileSystem = New Filesystem();

        $output->writeln('Generating README.md');
        $readMe = $this->twig->render('@CommonGround/repo/README.md.twig');
        $fileSystem->dumpFile('documentation/README.md', $readMe);
        $io->success(sprintf('Data written to %s/README.md.', '/app/documentation'));

        $output->writeln('Generating helm README.md');
        $readMe = $this->twig->render('@CommonGround/repo/HELMREADME.md.twig');
        $fileSystem->dumpFile('documentation/helm/README.md', $readMe);
        $io->success(sprintf('Data written to %s/helm/README.md.', '/app/documentation/helm'));

        $output->writeln('Generating .env');
        $env = $this->twig->render('@CommonGround/repo/env.env.twig');
        $fileSystem->dumpFile('documentation/.env', $env);
        $io->success(sprintf('Data written to %s/.env', '/app/documentation'));

        $output->writeln('Generating artifacthub-repo.yaml');
        $env = $this->twig->render('@CommonGround/helm/artifacthub-repo.yaml.twig');
        $fileSystem->dumpFile('documentation/artifacthub-repo.yaml', $env);
        $io->success(sprintf('Data written to %s/artifacthub-repo.yaml', '/app/documentation'));

        $output->writeln('Generating values.schema.json');
        $env = $this->twig->render('@CommonGround/helm/values.schema.json.twig');
        $fileSystem->dumpFile('documentation/values.schema.json', $env);
        $io->success(sprintf('Data written to %s/values.schema.json', '/app/documentation'));

        return 0;
    }
}
