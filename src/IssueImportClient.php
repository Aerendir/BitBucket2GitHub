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

namespace App;

use Github\Api\Issue;
use Github\Exception\MissingArgumentException;

/**
 * Calls the undocumented import API of GitHub.
 */
class IssueImportClient extends Issue
{
    /**
     * Create a new issue for the given username and repo.
     * The issue is assigned to the authenticated user. Requires authentication.
     *
     * @see http://developer.github.com/v3/issues/
     *
     * @param string $username   the username
     * @param string $repository the repository
     * @param array  $params     the new issue data
     *
     * @throws MissingArgumentException
     *
     * @return array information about the issue
     */
    public function import($username, $repository, array $params): array
    {
        if ( ! isset($params['issue'])) {
            throw new MissingArgumentException(['issue']);
        }

        return $this->post('/repos/' . rawurlencode($username) . '/' . rawurlencode($repository) . '/import/issues', $params);
    }
}
