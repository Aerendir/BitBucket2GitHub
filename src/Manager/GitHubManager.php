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

namespace App\Manager;

use App\IssueImportClient;
use Github\Api\Issue;
use Github\Api\Repo;
use Github\Client;
use Github\Exception\RuntimeException;

/**
 * Handles GitHub connection.
 */
class GitHubManager
{
    /** @var Client $client */
    private $client;

    /** @var Client $importClient */
    private $importClient;

    /** @var string $account */
    private $account;

    /** @var string $repository */
    private $repository;

    /** @var Issue $issuesClient */
    private $issuesClient;

    /** @var Issue\Milestones $milestonesClient */
    private $milestonesClient;

    /** @var array $milestonesList */
    private $milestonesList;

    /** @var IssueImportClient $issuesImportClient */
    private $issuesImportClient;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client       = $client;
        $this->importClient = new Client(null, 'golden-comet-preview');
    }

    /**
     * @param string $githubRepo
     */
    public function setRepositorySlug(string $githubRepo): void
    {
        [$account, $repo] = explode('/', $githubRepo);
        $this->account    = $account;
        $this->repository = $repo;
    }

    /**
     * @return bool
     */
    public function isAccessible(): bool
    {
        /** @var Repo $repoClient */
        $repoClient = $this->client->api('repo');

        try {
            $repoClient->show($this->account, $this->repository);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $user
     * @param string $pass
     */
    public function configureAuth(string $user, string $pass): void
    {
        $this->client->authenticate($user, $pass, Client::AUTH_HTTP_PASSWORD);
        $this->importClient->authenticate($user, $pass, Client::AUTH_HTTP_PASSWORD);
    }

    /**
     * @return bool
     */
    public function hasIssues(): bool
    {
        /** @var Repo $repoClient */
        $repoClient = $this->client->api('repo');
        $show       = $repoClient->show($this->account, $this->repository);

        return $show['has_issues'];
    }

    /**
     * @param string $checkingMilestone
     *
     * @return bool|int
     */
    public function milestoneExists(string $checkingMilestone)
    {
        if (null === $this->milestonesList) {
            $this->milestonesList = $this->getMilestonesClient()->all($this->account, $this->repository);
        }

        foreach ($this->milestonesList as $milestone) {
            if ($milestone['title'] === $checkingMilestone) {
                return $milestone['number'];
            }
        }

        return false;
    }

    /**
     * @param string $milestone
     *
     * @throws \Github\Exception\MissingArgumentException
     */
    public function milestoneCreate(string $milestone): void
    {
        $this->getMilestonesClient()->create($this->account, $this->repository, ['title' => $milestone]);
    }

    /**
     * @param int $issueId
     *
     * @return bool
     */
    public function issueExists(int $issueId): bool
    {
        try {
            $this->getIssuesClient()->show($this->account, $this->repository, $issueId);
        } catch (RuntimeException $e) {
            if (404 === $e->getCode()) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param array $issue
     *
     * @throws \Github\Exception\MissingArgumentException
     */
    public function createIssue(array $issue): void
    {
        try {
            $this->getIssueImportClient()->import($this->account, $this->repository, $issue);
        } catch (\Exception $e) {
            dd($e->getMessage(), $issue);
        }
    }

    /**
     * @return Issue
     */
    private function getIssuesClient(): Issue
    {
        if (null === $this->issuesClient) {
            $this->issuesClient = $this->client->api('issues');
        }

        return $this->issuesClient;
    }

    /**
     * @return IssueImportClient
     */
    private function getIssueImportClient(): IssueImportClient
    {
        if (null === $this->issuesImportClient) {
            $this->issuesImportClient = new IssueImportClient($this->importClient);
        }

        return $this->issuesImportClient;
    }

    /**
     * @return Issue\Milestones
     */
    private function getMilestonesClient(): Issue\Milestones
    {
        if (null === $this->milestonesClient) {
            $this->milestonesClient = $this->getIssuesClient()->milestones();
        }

        return $this->milestonesClient;
    }
}
