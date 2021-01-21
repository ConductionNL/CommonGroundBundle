<?php

// src/Command/CreateUserCommand.php

namespace Conduction\CommonGroundBundle\Command;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunVSBECommand extends Command
{
    private $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:vsbe:start')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a new helm chart.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a new hel chart from the helm template')
            ->setAliases(['app:vsbe:start'])
            ->setDescription('Kick off the VSBE')
            ->addArgument('object', null, InputOption::VALUE_REQUIRED, 'The object to start')
            ->addArgument('action', null, InputOption::VALUE_OPTIONAL, 'The CRUD action that belongs to the object', 'CREATE');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $version */
        $resource['action'] = $input->getArgument('action');
        $resource['object'] = $input->getArgument('object');

        $this->commonGroundService->createResource($resource, ['component'=>'vsbe', 'type'=>'results'], false, true, false);
    }
}
