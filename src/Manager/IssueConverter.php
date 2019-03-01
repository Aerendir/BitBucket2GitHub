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

/**
 * Converts an Issue and its details from BitBucket to GitHub.
 */
class IssueConverter
{
    private const ISSUE_TEMPLATE = <<<EOF
**[Original report](https://bitbucket.org/{repo}/issue/{id})**

{content}
EOF;

    private const COMMENT_TEMPLATE = <<<EOF
**Original comment by {author}.**

{content}
EOF;

    /** @var GitHubManager $gitHubManager */
    private $gitHubManager;

    /** @var string $repo */
    private $repo;

    /**
     * @param GitHubManager $gitHubManager
     */
    public function __construct(GitHubManager $gitHubManager)
    {
        $this->gitHubManager = $gitHubManager;
    }

    /**
     * @param string $repo
     */
    public function configure(string $repo): void
    {
        $this->repo = $repo;
    }

    /**
     * @param array $issue
     * @param array $comments
     * @param array $changes
     *
     * @throws \Exception
     *
     * @return array
     */
    public function convertIssue(array $issue, array $comments, array $changes): array
    {
        $migratedIssue    = $this->migrateIssue($issue, $changes);
        $migratedComments = $this->migrateComments($comments);

        return ['issue' => $migratedIssue, 'comments' => $migratedComments];
    }

    /**
     * @param array $migratingIssue
     * @param array $changes
     *
     * @throws \Exception
     *
     * @return array
     */
    private function migrateIssue(array $migratingIssue, array $changes): array
    {
        $issue = [
            'title'      => $migratingIssue['title'],
            'created_at' => $this->convertDate($migratingIssue['created_on']),
            'updated_at' => $this->convertDate($migratingIssue['updated_on']),
            'closed'     => $this->isClosed($migratingIssue['state']),
            'body'       => $this->formatIssueBody($migratingIssue),
        ];

        if ($migratingIssue['priority']) {
            $issue['labels'][] = $migratingIssue['priority'];
        }

        if ($migratingIssue['kind']) {
            $issue['labels'][] = $migratingIssue['kind'];
        }

        if ($migratingIssue['component'] && $migratingIssue['component']['name']) {
            // Commas are permitted in Bitbucket's components, but
            // they cannot be in GitHub labels, so they must be removed.
            $component = str_replace(',', '', $migratingIssue['component']['name']);

            // Github caps label lengths at 50, so we truncate anything longer
            $issue['labels'][] = substr($component, 0, 50);
        }

        if ($migratingIssue['version'] && $migratingIssue['version']['name']) {
            // Commas are permitted in Bitbucket's version, but
            // they cannot be in GitHub labels, so they must be removed.
            $version = str_replace(',', '', $migratingIssue['version']['name']);

            // Github caps label lengths at 50, so we truncate anything longer
            $issue['labels'][] = substr($version, 0, 50);
        }

        if ($migratingIssue['milestone'] && $migratingIssue['milestone']['name']) {
            $milestoneId = $this->gitHubManager->milestoneExists($migratingIssue['milestone']['name']);

            if (false !== $milestoneId) {
                $issue['milestone'] = $milestoneId;
            }
        }

        if ($issue['closed']) {
            $issue['closed_at'] = $this->findClosedDate($changes) ?? $issue['updated_at'];
        }

        return $issue;
    }

    /**
     * @param array $migratingComments
     *
     * @throws \Exception
     *
     * @return array
     */
    private function migrateComments(array $migratingComments): array
    {
        $migratedComments = [];
        foreach ($migratingComments as $migratingCommentsResponse) {
            foreach ($migratingCommentsResponse['values'] as $migratingComment) {
                if (isset($migratingComment['content']) && null !== $migratingComment['content']['raw']) {
                    $migratedComments[] = [
                        'created_at' => $this->convertDate($migratingComment['created_on']),
                        'body'       => $this->formatCommentBody($migratingComment),
                    ];
                }
            }
        }

        return $migratedComments;
    }

    /**
     * @param array $changesResponses
     *
     * @throws \Exception
     *
     * @return bool|string
     */
    private function findClosedDate(array $changesResponses)
    {
        foreach ($changesResponses as $changes) {
            foreach ($changes['values'] as $change) {
                if (
                    isset($change['changes']['state']) &&
                    false === $this->isClosed($change['changes']['state']['old']) &&
                    true === $this->isClosed($change['changes']['state']['new'])
                ) {
                    return $this->convertDate($change['created_on']);
                }
            }
        }

        return false;
    }

    /**
     * @param string $state
     *
     * @return bool
     */
    private function isClosed(string $state): bool
    {
        return false === in_array($state, ['open', 'new', 'on hold']);
    }

    /**
     * @param array $migratingIssue
     *
     * @return string
     */
    private function formatIssueBody(array $migratingIssue): string
    {
        $content = $migratingIssue['content']['raw'];
        $content = $this->convertLinks($content);
        $content = str_replace(['{content}', '{repo}', '{id}'], [$content, $this->repo, $migratingIssue['id']], self::ISSUE_TEMPLATE);

        return $content;
    }

    /**
     * @param array $migratingComment
     *
     * @return string
     */
    private function formatCommentBody(array $migratingComment): string
    {
        $content = $migratingComment['content']['raw'];
        $content = $this->convertLinks($content);
        $content = str_replace(['{content}', '{author}'], [$content, $migratingComment['user']['username']], self::COMMENT_TEMPLATE);

        return $content;
    }

    /**
     * Convert absolute links to other issues related to this repository to
     * relative links ("#<id>").
     *
     * @param string $content
     *
     * @return string
     */
    private function convertLinks(string $content): string
    {
        $regex = sprintf('~https://bitbucket.org/%s/issues/(\d+)/[\S]+~', $this->repo);
        preg_match_all($regex, $content, $matches);

        foreach ($matches[0] as $key => $url) {
            $content = str_replace($url, sprintf('#%s', $matches[1][$key]), $content);
        }

        return $content;
    }

    /**
     * @param string $date
     *
     * @throws \Exception
     *
     * @return string
     */
    private function convertDate(string $date): string
    {
        $object = new \DateTime($date);

        return sprintf('%sT%sZ', $object->format('Y-m-d'), $object->format('H:i:s'));
    }
}
