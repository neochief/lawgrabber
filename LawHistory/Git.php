<?php

namespace LawHistory;

use GitHub;
use Illuminate\Console\Command;

class Git {

    /**
     * @var Command
     */
    private $command;

    /**
     * @var string
     */
    public $github_account = 'RadaData';

    /**
     * @var string
     */
    public $repository_name;

    /**
     * @var array
     */
    public $branch_status = [];

    /**
     * @var string
     */
    public $current_branch = 'master';

    /**
     * GitHistory constructor.
     * @param $console
     */
    public function __construct(Command $console, $repository_name)
    {
        $this->command = $console;
        $this->repository_name = $repository_name;
    }

    public function getHistoryDir()
    {
        return base_path() . '/' . trim(env('HISTORY_DIR', '../history'), '/');
    }

    public function gitReset()
    {
        try {
            GitHub::repo()->remove($this->github_account, $this->repository_name);
            $this->command->info('Old repository purged.');
        }
        catch (\Exception $e) {}

        GitHub::repo()->create($this->repository_name, 'Ukrainian law repository.', 'http://radadata.com', true, $this->github_account);
        $this->command->info('New repository created.');

        $dir = $this->getHistoryDir();
        if (is_dir($dir)) {
            exec('rm -rf ' . $dir);
        }
        mkdir($dir, 0777, true);
        exec('cd ' . $dir . '; git init; git remote add origin git@github.com:' . $this->github_account . '/' . $this->repository_name . '.git; ');
        $this->command->info("Git initialized.");

        copy(__DIR__ . '/README-' . $this->repository_name . '.md', $dir . '/README.md');
        $command = "cd $dir; git add . ; GIT_AUTHOR_DATE='1970-01-01T00:00:00+0000' GIT_COMMITTER_DATE='1970-01-01T00:00:00+0000' git commit -m 'Перший комміт.' ; git push -q -f origin master;";
        exec($command);
    }

    public function gitCommit($date, $message)
    {
        $dir = $this->getHistoryDir();
        $date = $this->normalizeCommitDate($date);
        $command = "cd $dir; git add . ; GIT_AUTHOR_DATE=$date GIT_COMMITTER_DATE=$date git commit -m '$message'";
        exec($command);
        $this->branch_status[$this->current_branch] = false;
        $this->command->info("Commit: $message");
    }

    /**
     * Commit time is limitted by the year of 1971, so we should adjust earlier laws to that date.
     * @param $date
     * @return string
     */
    public function normalizeCommitDate($date) {
        $time = max(strtotime('1970-01-01 00:00:01+0000'), strtotime($date));
        return date('Y-m-d\TH:i:s+0000', $time);
    }

    public function gitCheckout($branch = 'master')
    {
        if ($branch == $this->current_branch) {
            return;
        }
        
        $dir = $this->getHistoryDir();
        $command = "cd $dir; git checkout master -q";
        if ($branch != 'master') {
            $command .= "; git checkout -B '$branch'";
        }
        exec($command);

        $this->current_branch = $branch;
        if (!isset($this->branch_status[$branch])) {
            $this->branch_status[$branch] = false;
        }
        
        $this->command->info("Checkout: $branch");
    }

    public function gitCheckIfOutdatedAndPush($branch = 'master')
    {
        if ($this->branch_status[$branch]) {
            return;
        }
        $this->gitCheckout($branch);
        $this->gitPush($branch);
    }

    public function gitPush($branch = 'master')
    {
        if ($this->branch_status[$branch]) {
            return;
        }
        
        $dir = $this->getHistoryDir();
        $command = "cd $dir; git checkout '$branch'; git push -u -f origin '$branch'";
        exec($command);
        $this->branch_status[$branch] = true;
        $this->command->info("Push: $branch");
    }

    public function gitSendPullRequest($branch, $title, $message)
    {
        $pullRequest = GitHub::pull_requests()->create($this->github_account, $this->repository_name, array(
            "title" => $title,
            "head" => $branch,
            "base" => "master",
            "body" => $message,
        ));
        $this->command->info("Pull request created: #{$pullRequest['id']}");
    }
}