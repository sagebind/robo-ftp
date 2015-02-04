<?php
namespace RoboFtp;

use Robo\Result;
use Robo\Task\BaseTask;
use Symfony\Component\Finder\Finder;

/**
 * Task for deploying files to a remote server over FTP.
 */
class FtpDeployTask extends BaseTask
{
    /**
     * @var string The FTP host to connect to.
     */
    protected $host;

    /**
     * @var string The user to log in to the server as.
     */
    protected $user;

    /**
     * @var string The password to log in with.
     */
    protected $password;

    /**
     * @var string The port to connect over.
     */
    protected $port = 21;

    /**
     * @var string The directory to deploy to on the remote server.
     */
    protected $targetDirectory = '/';

    /**
     * @var string Indicates if the connection should be established over SSL.
     */
    protected $useSSL = true;

    /**
     * @var string Indicates if files should be skipped based on size.
     */
    protected $skipSizeEqual = false;

    /**
     * @var string Indicates if files should be skipped based on modified date.
     */
    protected $skipUnmodified = false;

    /**
     * @var string The finder instance for iterating over input files.
     */
    protected $finder;

    /**
     * @var array A list of concrete files to upload.
     */
    protected $files;

    /**
     * Creates a new FTP deploy task instance.
     *
     * @param string $host     The FTP host to connect to.
     * @param string $user     The user to log in to the server as.
     * @param string $password The password to log in with.
     */
    public function __construct($host, $user, $password)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->finder = new Finder();
        $this->files = [];
    }

    /**
     * Sets the port to connect over.
     *
     * @param  int $port The port number.
     *
     * @return FtpDeployTask The current task.
     */
    public function port($port)
    {
        $this->port = (int)$port;
        return $this;
    }

    /**
     * Sets the target path on the remote server to deploy to.
     *
     * @param string $directory
     *
     * @return FtpDeployTask The current task.
     */
    public function dir($directory)
    {
        $this->targetDirectory = '/' . trim(preg_replace('%[\\\\/]+%', '/', $directory), '/');
        return $this;
    }

    /**
     * Adds a directory to upload.
     *
     * @param string $directory
     *
     * @return FtpDeployTask The current task.
     */
    public function from($directory)
    {
        $this->finder->in($directory);
        return $this;
    }

    /**
     * Adds specific files to be uploaded.
     *
     * @param  array|string $files A file or list of files to upload.
     *
     * @return FtpDeployTask The current task.
     */
    public function files($files)
    {
        $files = is_array($files) ? $files : [(string)$files];
        $this->files = array_merge($this->files, $files);
        return $this;
    }

    /**
     * Adds a file pattern that should be included.
     *
     * @param string $pattern
     *
     * @return FtpDeployTask The current task.
     */
    public function matching($pattern)
    {
        $this->finder->path($pattern);
        return $this;
    }

    /**
     * Adds a file pattern that should be excluded.
     *
     * @param string $pattern
     *
     * @return FtpDeployTask The current task.
     */
    public function exclude($pattern)
    {
        $this->finder->exclude($pattern);
        return $this;
    }

    /**
     * Enables FTPS file transfer.
     *
     * @return FtpDeployTask The current task.
     */
    public function secure($secure = true)
    {
        $this->useSSL = $secure;
        return $this;
    }

    /**
     * Skips uploading files whose size is equal to the existing remote file.
     *
     * @return FtpDeployTask The current task.
     */
    public function skipSizeEqual()
    {
        $this->skipSizeEqual = true;
        return $this;
    }

    /**
     * Skips uploading files whose modification date is equal or older than the existing remote file.
     *
     * @return FtpDeployTask The current task.
     */
    public function skipUnmodified()
    {
        $this->skipUnmodified = true;
        return $this;
    }

    /**
     * Enables or disables ignoring of common VCS-related files.
     *
     * @return FtpDeployTask The current task.
     */
    public function ignoreVCS($ignore = true)
    {
        $finder->ignoreVCS($ignore);
        return $true;
    }

    /**
     * Runs the FTP deploy task.
     *
     * @return Result The result of the task.
     */
    public function run()
    {
        $ftp = new \Ftp();

        // connect to the server
        try {
            if ($this->useSSL) {
                $ftp->sslConnect($this->host);
            } else {
                $ftp->connect($this->host);
            }
            $ftp->login($this->user, $this->password);

            // create the target directory if it does not exist
            $ftp->chdir('/');
            if (!$ftp->isDir($this->targetDirectory)) {
                $this->printTaskInfo('Creating directory: ' . $this->targetDirectory);
                $ftp->mkDirRecursive($this->targetDirectory);
            }

            // scan and index files in finder
            $this->printTaskInfo('Scanning files to upload...');
            // add discrete files
            $this->finder->append(new \ArrayIterator($this->files));
            // directories first
            $this->finder->sortByType();

            // display summary before deploying
            $this->printTaskInfo(sprintf('Deploying %d files to "%s://%s@%s%s"...',
                $this->finder->count(),
                $this->useSSL ? 'ftps' : 'ftp',
                $this->user,
                $this->host,
                $this->targetDirectory));

            // upload each file, starting with directories
            if ($this->finderReady) {
                foreach ($this->finder as $file) {
                    $this->upload($ftp, $file);
                }
            }

            // close the connection
            $ftp->close();
        } catch (\FtpException $e) {
            return Result::error($this, 'Error: ' . $e->getMessage());
        }

        // success!
        return Result::success($this, 'All files deployed.');
    }

    /**
     * Uploads a file or directory to an FTP connection.
     */
    protected function upload(\Ftp $ftp, \SplFileInfo $file)
    {
        // enter into passive mode
        $ftp->pasv(true);

        // move to the file's parent directory
        $ftp->chdir($this->targetDirectory . '/' . $file->getRelativePath());

        // check if the file exists
        $fileExists = in_array($file->getBasename(), $ftp->nlist('.'));

        // check if the file is a directory
        if ($file->isDir()) {
            // create the directory if it does not exist
            if (!$fileExists) {
                $this->printTaskInfo('Creating directory: ' . $file->getRelativePathname());

                // create directory
                $ftp->mkdir($file->getBasename());
            }
        } else {
            // if the file already exists, check our skip options
            if ($fileExists) {
                // skip the file if the file sizes are equal
                if ($this->skipSizeEqual && $ftp->size($file->getBasename()) === $file->getSize()) {
                    return;
                }

                // skip the file if modified time is same or newer than source
                if ($this->skipUnmodified && $ftp->mdtm($file->getBasename()) >= $file->getMTime()) {
                    return;
                }
            }

            // try to upload the file
            $this->printTaskInfo('Uploading: ' . $file->getRelativePathname());
            if (!$ftp->put($file->getBasename(), $file->getRealpath(), FTP_BINARY)) {
                // something went wrong
                return Result::error($this, 'Failed while uploading file ' . $file->getRelativePathname());
            }
        }
    }
}
