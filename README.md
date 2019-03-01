<p align="center">
    <a href="http://www.serendipityhq.com" target="_blank">
        <img src="http://www.serendipityhq.com/assets/open-source-projects/Logo-SerendipityHQ-Icon-Text-Purple.png">
    </a>
</p>

BITBUCKET 2 GITHUB ISSUES MIGRATOR
==================================

This is a small app tht will migrate your issues from BitBucket to GitHub.

To use this app, follow the following steps:

1. Download the repository:

       git clone https://github.com/Aerendir/BitBucket2GitHub.git

2. Install the Composer dependencies:

       composer install

3. The use the command `bb2gh:migrate` to start the migration, providing the relevant information

       $ bin/console bb2gh:migrate --bb-repo Aerendir/bitbucket-source-repo --bb-user Aerendir --bb-pass Y0uRP4$s --gh-repo Aerendir/github-destination-repo --gh-user Aerendir --gh-pass Y0uRP4$s

This command will start the migration

    ...
    Issue [263] dummy issue: Gap filled
    Issue [264] dummy issue: Gap filled
    Issue [264] This is a real issue on BitBuckets: Synched
     265/660 [===========>----------------]  40%
    Issue [265] This is another real issue on BitBucket: Synching...
    Checking if the issue exists on Github... 
    Retrieving the comments of the issue...
        Calling page 1
    Retrieving the changes of the issue...
        Calling page 1
    Preparing the issue for GitHub
    Creating the issue on GitHub...

The script will be able to resume an interrupted import.

NOTE: You can safely pass the passwords to the command as it is meant to be used only locally.

Keep attention because the passwords will be logged in the console.

ATTENTION: Requirements
-----------------------

The destination repo on GitHub must be either completely empty or have the issues imported from BitBucket from a noot completed migration.

The application relies on the IDs, so, if you start the import, then create an issue on the destination repo on GitHub and then start importing again, the issues will lose synch.

Features
--------

1. Can synch all the issues from BitBucket to GitHub;
2. Can link issues referenced from other issues;
3. Can resume an interrupted migration;
4. Can import comments of issues
5. Uses the undocumented GitHub's import API to avoid reach the rate limits (https://gist.github.com/jonmagic/5282384165e0f86ef105)

Credits
-------

This script is heavilly inspired by [BitBucket Issue Migration](https://github.com/jeffwidman/bitbucket-issue-migration) python script.

Unfortunately, during the migration of one of my repositories, the migration stopped and that script isn't able to resume an interrupted migration.

So, I decided to write my own version in PHP.

This app doesn't have all the features provided by the original python script: if you like to contribute, feel free to send a PR. 
