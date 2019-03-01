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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

/**
 * Handles BitBucket connection.
 */
class BitBucketManager
{
    private const BASIC_URL = 'https://api.bitbucket.org/2.0';

    /** @var Client $client */
    private $client;

    /** @var string $account */
    private $account;

    /** @var string $repository */
    private $repository;

    /** @var string $repositoryUrl */
    private $repositoryUrl;

    /** @var string $user */
    private $user;

    /** @var string $pass */
    private $pass;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $bitbucketRepo
     */
    public function setRepositorySlug(string $bitbucketRepo): void
    {
        [$account, $repo] = explode('/', $bitbucketRepo);
        $this->account    = $account;
        $this->repository = $repo;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        try {
            $this->client->get($this->getRepositoryUrl());
        } catch (ClientException $e) {
            if (403 === $e->getCode()) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param string $user
     * @param string $pass
     */
    public function configureAuth(string $user, string $pass): void
    {
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * @return int
     */
    public function getTotalIssues(): int
    {
        $issues = $this->callEndpoint('issues');

        $decoded = json_decode($issues->getBody()->getContents(), true);

        return $decoded['size'];
    }

    /**
     * @return array
     */
    public function getMilestones(): array
    {
        $milestones = $this->callEndpoint('milestones');

        return json_decode($milestones->getBody()->getContents(), true);
    }

    /**
     * @param int $page
     *
     * @return array
     */
    public function getIssues(int $page = 1): array
    {
        $issues = $this->callEndpoint(sprintf('issues?page=%s&sort=id', $page));

        return json_decode($issues->getBody()->getContents(), true);
    }

    /**
     * @param int                  $issueId
     * @param ConsoleSectionOutput $section
     *
     * @return array
     */
    public function getIssueComments(int $issueId, ConsoleSectionOutput $section): array
    {
        $comments = [];
        $nextUrl  = sprintf('issues/%s/comments?page=1&sort=id', $issueId);
        $page     = 1;

        while ($nextUrl) {
            $section->writeln(sprintf('    Calling <info>page %s</info>', $page));
            $response        = $this->callEndpoint($nextUrl);
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            $comments[]      = $decodedResponse;
            ++$page;
            $nextUrl = $decodedResponse['next'] ?? null;
        }

        return $comments;
    }

    /**
     * @param int                  $issueId
     * @param ConsoleSectionOutput $section
     *
     * @return array
     */
    public function getIssueChanges(int $issueId, ConsoleSectionOutput $section): array
    {
        $changes = [];
        $nextUrl = sprintf('issues/%s/changes?page=1&sort=id', $issueId);
        $page    = 1;

        while ($nextUrl) {
            $section->writeln(sprintf('    Calling <info>page %s</info>', $page));
            $response        = $this->callEndpoint($nextUrl);
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            $changes[]       = $decodedResponse;
            ++$page;
            $nextUrl = $decodedResponse['next'] ?? null;
        }

        return $changes;
    }

    /**
     * @return string
     */
    private function getRepositoryUrl(): string
    {
        if (null === $this->repositoryUrl) {
            $this->repositoryUrl = sprintf('%s/repositories/%s/%s', self::BASIC_URL, $this->account, $this->repository);
        }

        return $this->repositoryUrl;
    }

    /**
     * @param string $endpoint
     *
     * @return ResponseInterface
     */
    private function callEndpoint(string $endpoint): ResponseInterface
    {
        if (null !== $this->user) {
            $options['auth'] = [$this->user, $this->pass];
        }

        if (false === strpos($endpoint, $this->getRepositoryUrl())) {
            $endpoint = $this->getRepositoryUrl() . '/' . $endpoint;
        }

        return $this->client->get($endpoint, $options);
    }
}
