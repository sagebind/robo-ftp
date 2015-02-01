<?php
namespace RoboFtp;

use Robo\Task\BaseTask;
use Symfony\Component\Finder\Finder;

/**
 * Task for deploying files to a remote server over FTP.
 */
class FtpDeployTask extends BaseTask
{
    protected $host;
    protected $user;
    protected $password;
    protected $directory = '/';
    protected $useSSL = true;
    protected $dryRun = false;
    protected $finder;
    protected $sizeDifferent = false;

    public function __construct($host, $user, $password)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->finder = new Finder();
    }

    /**
     * Sets the target path on the remote server to deploy to.
     *
     * @param string $directory
     *
     * @return FtpDeployTask
     */
    public function dir($directory)
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * Adds a directory to upload.
     *
     * @param string $directory
     *
     * @return FtpDeployTask
     */
    public function in($directory)
    {
        $this->finder->in($directory);
        return $this;
    }

    /**
     * Adds a file pattern that should be included.
     *
     * @param string $pattern
     *
     * @return FtpDeployTask
     */
    public function path($pattern)
    {
        $this->finder->path($pattern);
        return $this;
    }

    /**
     * Adds a file pattern that should be excluded.
     *
     * @param string $pattern
     *
     * @return FtpDeployTask
     */
    public function exclude($pattern)
    {
        $this->finder->exclude($pattern);
        return $this;
    }

    /**
     * Enables FTPS file transfer.
     *
     * @return FtpDeployTask
     */
    public function secure($secure = true)
    {
        $this->useSSL = $secure;
        return $this;
    }

    /**
     * Enables FTPS file transfer.
     *
     * @return FtpDeployTask
     */
    public function sizeDifferent()
    {
        $this->sizeDifferent = true;
        return $this;
    }

    /**
     * Enables or disables ignoring of common VCS-related files.
     *
     * @return FtpDeployTask
     */
    public function ignoreVCS($ignore = true)
    {
        $finder->ignoreVCS($ignore);
        return $true;
    }

    /**
     * Sets the task as a dry run.
     *
     * @return FtpDeployTask
     */
    public function dryRun()
    {
        $this->dryRun = true;
        return $this;
    }

    /**
     * Runs the FTP deploy task.
     */
    public function run()
    {
        $ftp = new \Ftp();

        // connect to the server
        if ($this->useSSL) {
            $ftp->sslConnect($this->host);
        } else {
            $ftp->connect($this->host);
        }

        // log in to the server
        $ftp->login($this->user, $this->password);

        // sort files to upload by type; directories first
        $this->finder->sortByType();

        // upload each file, starting with directories
        foreach ($this->finder as $file) {
            // enter into passive mode
            $ftp->pasv(true);

            // move to the file's parent directory
            $ftp->chdir($this->directory . '/' . $file->getRelativePath());

            // check if the file exists
            $fileExists = in_array($file->getBasename(), $ftp->nlist('.'));

            // check if the file is a directory
            if ($file->isDir()) {
                // create the directory if it does not exist
                if (!$fileExists) {
                    $this->printTaskInfo(sprintf('Create directory: "%s"', $this->directory . '/' . $file->getRelativePathname()));

                    // create directory if not dry run
                    if ($this->dryRun) {
                        $ftp->mkdir($file->getBasename());
                    }
                }
            } else {
                // check if the destination file already exists
                if ($fileExists) {
                    $this->printTaskInfo(sprintf('The remote file "%s" already exists. Skipping.', $this->directory . '/' . $file->getRelativePathname()));
                    continue;
                }

                // upload file if not dry run
                $this->printTaskInfo(sprintf('Uploading: "%s" <- "%s"', $this->directory, $file->getRelativePathname()));
                if (!$this->dryRun) {
                    $ftp->put($file->getBasename(), $file->getRealpath(), FTP_BINARY);
                }
            }
        }

        // close the connection
        $ftp->close();
    }
}
