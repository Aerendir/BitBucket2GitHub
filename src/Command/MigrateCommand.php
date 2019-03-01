<?php

declare(strict_types=1);

/*
 * This file is part of BitBucket 2 GitHub.
 *
 * Copyright Adamo Aerendir Crespi 2019.
 *
 * @author    Adamo Aerendir Crespi <hello@aerendir.me>
 * @copyright Copyright (C) 2019 Aerendir. All rights reserved.
 * @license   MIT
 */

namespace App\Command;

use App\Manager\BitBucketManager;
use App\Manager\GitHubManager;
use App\Manager\IssueConverter;
use SerendipityHQ\Bundle\ConsoleStyles\Console\Style\SerendipityHQStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Analyze a Domain.
 */
class MigrateCommand extends Command
{
    public const NAME = 'bb2gh:migrate';

    private const DUMMY_ISSUE = [
        'issue' => [
            'title'  => 'dummy issue',
            'body'   => 'filler issue created by bb2gh',
            'closed' => true,
        ],
    ];

    /** @var SerendipityHQStyle $ioWriter */
    private $ioWriter;

    /** @var BitBucketManager $bitBucketManager */
    private $bitBucketManager;

    /** @var GitHubManager $gitHubManager */
    private $gitHubManager;

    /** @var IssueConverter $issueConverter */
    private $issueConverter;

    /** @var int $expectedIssueId */
    private $expectedIssueId = 1;

    /** @var int $bitBucketTotalIssues */
    private $bitBucketTotalIssues;

    /**
     * @param BitBucketManager $bitBucketManager
     * @param GitHubManager    $gitHubManager
     * @param IssueConverter   $issueConverter
     */
    public function __construct(BitBucketManager $bitBucketManager, GitHubManager $gitHubManager, IssueConverter $issueConverter)
    {
        parent::__construct();

        $this->bitBucketManager = $bitBucketManager;
        $this->gitHubManager    = $gitHubManager;
        $this->issueConverter   = $issueConverter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Migrates issues from BitBucket to GitHub.')
            ->addOption('bb-repo', null, InputOption::VALUE_OPTIONAL, 'The repository on BitBucket from which to migrate issues. Ex: "Aerendir/bb2gh".')
            ->addOption('bb-user', null, InputOption::VALUE_OPTIONAL, 'The username of the user who has access to the repository on BitBucket.')
            ->addOption('bb-pass', null, InputOption::VALUE_OPTIONAL, 'The password of the user who has access to the repository on BitBucket.')
            ->addOption('gh-repo', null, InputOption::VALUE_OPTIONAL, 'The repository on GitHub to which to migrate issues. Ex: "Aerendir/bb2gh".')
            ->addOption('gh-user', null, InputOption::VALUE_OPTIONAL, 'The username of the user who has access to the repository on GitHub.')
            ->addOption('gh-pass', null, InputOption::VALUE_OPTIONAL, 'The password of the user who has access to the repository on GitHub.');
    }

    /**
     * @param InputInterface                $input
     * @param ConsoleOutput|OutputInterface $output
     *
     * @throws \Github\Exception\MissingArgumentException
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->ioWriter = new SerendipityHQStyle($input, $output);
        $questionHelper = $this->getHelper('question');

        $this->ioWriter->title('Starting ' . self::NAME);

        $bitBucketRepo = $input->getOption('bb-repo');
        if (null === $bitBucketRepo) {
            $askBitBucketRepo = new Question('Please, enter the name of the BitBucket repo from which you would like to import the issues. Example: Aerendir/bb2gh: ');
            $bitBucketRepo    = $questionHelper->ask($input, $output, $askBitBucketRepo);
        }
        $this->issueConverter->configure($bitBucketRepo);

        $this->ioWriter->writeln(sprintf('Checking repo <info>%s</info> on BitBucket is accessible...', $bitBucketRepo));
        $this->bitBucketManager->setRepositorySlug($bitBucketRepo);

        if (false === $this->bitBucketManager->isPublic()) {
            $this->ioWriter->writeln(sprintf('Repo <info>%s</info> on BitBucket is private', $bitBucketRepo));

            $bitBucketUser = $input->getOption('bb-user');
            if (null === $bitBucketUser) {
                $askBitBucketUser = new Question('Please, enter the BitBucket\'s User: ');
                $bitBucketUser    = $questionHelper->ask($input, $output, $askBitBucketUser);
            }

            $this->ioWriter->writeln(sprintf('BitBucket user: <info>%s</info>', $bitBucketUser));

            $bitBucketPass = $input->getOption('bb-pass');
            if (null === $bitBucketPass) {
                $askBitBucketPass = new Question('Please, enter the BitBucket\'s Password: ');
                $askBitBucketPass->setHidden(true);
                $askBitBucketPass->setHiddenFallback(false);
                $bitBucketPass = $questionHelper->ask($input, $output, $askBitBucketPass);
            }

            $this->ioWriter->writeln(sprintf('Configuring access to BitBucket with user <info>%s</info> and pass <info>%s</info>', $bitBucketUser, $bitBucketPass));
            $this->bitBucketManager->configureAuth($bitBucketUser, $bitBucketPass);
        }

        $this->bitBucketTotalIssues = $this->bitBucketManager->getTotalIssues();

        $this->ioWriter->writeln(sprintf('Repo <info>%s</info> on BitBucket contains <info>%s</info> issues', $bitBucketRepo, $this->bitBucketTotalIssues));

        $gitHubRepo = $input->getOption('gh-repo');
        if (null === $gitHubRepo) {
            $askGitHubRepo = new Question('Please, enter the name of the GitHub repo to which you would like to import the issues. Example: Aerendir/bb2gh: ');
            $gitHubRepo    = $questionHelper->ask($input, $output, $askGitHubRepo);
        }

        $this->gitHubManager->setRepositorySlug($gitHubRepo);

        if (false === $this->gitHubManager->isAccessible()) {
            $this->ioWriter->writeln(sprintf('Repo <info>%s</info> on GitHub is private (or it doesn\'t exist at all)', $gitHubRepo));
            $gitHubUser = $input->getOption('gh-user');
            if (null === $gitHubUser) {
                $askGitHubUser = new Question('Please, enter the GitHub\'s User: ');
                $gitHubUser    = $questionHelper->ask($input, $output, $askGitHubUser);
            }

            $this->ioWriter->writeln(sprintf('GitHub user: <info>%s</info>', $gitHubUser));

            $gitHubPass = $input->getOption('gh-pass');
            if (null === $gitHubPass) {
                $askGitHubPass = new Question('Please, enter the GitHub\'s Password: ');
                $askGitHubPass->setHidden(true);
                $askGitHubPass->setHiddenFallback(false);
                $gitHubPass = $questionHelper->ask($input, $output, $askGitHubPass);
            }

            $this->ioWriter->writeln(sprintf('Configuring access to GitHub with user <info>%s</info> and pass <info>%s</info>', $gitHubUser, $gitHubPass));
            $this->gitHubManager->configureAuth($gitHubUser, $gitHubPass);
        }

        $this->ioWriter->writeln('');
        $this->ioWriter->writeln('Synching <info>milestones</info>');
        $this->ioWriter->writeln(sprintf('Retrieving milestones from <info>BitBucket:%s</info>', $bitBucketRepo));
        $milestones = $this->bitBucketManager->getMilestones();

        foreach ($milestones['values'] as $milestone) {
            $milestoneSection = $output->section();
            $milestoneSection->write(sprintf('Checking milestone <info>BitBucket:%s</info>: ', $milestone['name']));
            if (false === $this->gitHubManager->milestoneExists($milestone['name'])) {
                $milestoneSection->write(sprintf('Doesn\'t exis: creating it...'));
                $this->gitHubManager->milestoneCreate($milestone['name']);
            }

            $milestoneSection->overwrite(sprintf('Checking milestone <info>BitBucket:%s</info>: V', $milestone['name']));
        }

        $this->ioWriter->writeln('');
        $this->ioWriter->writeln('Synching <info>issues</info>');
        $this->ioWriter->writeln(sprintf('Retrieving issues from <info>BitBucket:%s</info>', $bitBucketRepo));

        $issuesSection = $output->section();
        $progress      = new ProgressBar($output, $this->bitBucketTotalIssues);
        $progress->advance();

        $issues = $this->bitBucketManager->getIssues();

        $this->processIssues($issues, $progress, $issuesSection, $output);

        $progress->finish();
    }

    /**
     * @param array                $issuesResponse
     * @param ProgressBar          $progress
     * @param ConsoleSectionOutput $issuesSection
     * @param ConsoleOutput        $output
     *
     * @throws \Github\Exception\MissingArgumentException
     */
    private function processIssues(array $issuesResponse, ProgressBar $progress, ConsoleSectionOutput $issuesSection, ConsoleOutput $output): void
    {
        foreach ($issuesResponse['values'] as $issue) {
            if (false === $this->isExpectedIssueId($issue)) {
                while (false === $this->isExpectedIssueId($issue)) {
                    $this->fillTheGap($issue, $issuesSection, $output);
                    ++$this->bitBucketTotalIssues;
                    $progress->setMaxSteps($this->bitBucketTotalIssues);
                    $progress->advance();
                }
            }

            $this->synchIssue($issue, $issuesSection, $output);
            $progress->advance();
        }

        if (isset($issuesResponse['next'])) {
            $issues = $this->bitBucketManager->getIssues($issuesResponse['page'] + 1);
            $this->processIssues($issues, $progress, $issuesSection, $output);
        }
    }

    /**
     * @param array $issue
     *
     * @return bool
     */
    private function isExpectedIssueId(array $issue): bool
    {
        return $this->expectedIssueId === $issue['id'];
    }

    /**
     * @param array                $issue
     * @param ConsoleSectionOutput $issuesSection
     * @param ConsoleOutput        $output
     *
     * @throws \Github\Exception\MissingArgumentException
     */
    private function fillTheGap(array $issue, ConsoleSectionOutput $issuesSection, ConsoleOutput $output): void
    {
        $section = $output->section();
        $section->writeln('');
        $section->writeln(sprintf('Issue <info>[%s] </info>: This is a gap: filling it...', $this->expectedIssueId));
        $section->writeln('Checking if the issue exists on Github... ');
        if ($this->gitHubManager->issueExists($this->expectedIssueId)) {
            $section->clear();
            $issuesSection->writeln(sprintf('Issue <info>[%s] Dummy issue</info>: Gap already filled', $this->expectedIssueId));
            ++$this->expectedIssueId;

            return;
        }

        $section->writeln('Creating the issue on GitHub...');

        $this->gitHubManager->createIssue(self::DUMMY_ISSUE);
        ++$this->expectedIssueId;

        $section->clear();
        $issuesSection->writeln(sprintf('Issue <info>[%s] %s</info>: Gap filled', $this->expectedIssueId, self::DUMMY_ISSUE['issue']['title']));
    }

    /**
     * @param array                $issue
     * @param ConsoleSectionOutput $issuesSection
     * @param ConsoleOutput        $output
     *
     * @throws \Github\Exception\MissingArgumentException
     * @throws \Exception
     */
    private function synchIssue(array $issue, ConsoleSectionOutput $issuesSection, ConsoleOutput $output): void
    {
        $section = $output->section();
        $section->writeln('');
        $section->writeln(sprintf('Issue <info>[%s] %s</info>: Synching...', $issue['id'], $issue['title']));
        $section->writeln('Checking if the issue exists on Github... ');
        if ($this->gitHubManager->issueExists($issue['id'])) {
            $section->clear();
            $issuesSection->writeln(sprintf('Issue <info>[%s] %s</info>: Already exists', $issue['id'], $issue['title']));
            ++$this->expectedIssueId;

            return;
        }

        $section->writeln('Retrieving the comments of the issue...');
        $comments = $this->bitBucketManager->getIssueComments($issue['id'], $section);
        $section->writeln('Retrieving the changes of the issue...');
        $changes = $this->bitBucketManager->getIssueChanges($issue['id'], $section);

        $section->writeln('Preparing the issue for GitHub');
        $migratedIssue = $this->issueConverter->convertIssue($issue, $comments, $changes);

        $section->writeln('Creating the issue on GitHub...');

        $this->gitHubManager->createIssue($migratedIssue);
        ++$this->expectedIssueId;

        $section->clear();
        $issuesSection->writeln(sprintf('Issue <info>[%s] %s</info>: Synched', $issue['id'], $issue['title']));
    }
}
