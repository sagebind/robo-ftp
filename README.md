# Robo FTP Deploy Task
A simple task for the [Robo](http://robo.li) task runner for deploying files to a remote server using FTP. Useful for shared hosting servers if you do not have SSH access, or for if you need better platform independence.

## Installation
Add the package to your list of dependencies:

```sh
composer require --dev coderstephen/robo-ftp
```

This task uses [`dg/ftp-php`](http://packagist.org/packages/dg/ftp-php) for establishing FTP connections, which is a thin wrapper around the [built-in FTP PHP extension](http://php.net/ftp). Most PHP installations are compiled with this extension, so this task should be able to be run just about anywhere with a PHP interpreter.

## Usage
Just include the `FtpDepoly` trait in your `RoboFile.php` file and run an FTP deploy task using `$this->taskFtpDeploy()`.

```php
class RoboFile extends \Robo\Tasks
{
    use RoboFtp\FtpDeploy;

    function deploy()
    {
        $ftp = $this->taskFtpDeploy('host', 'user', 'password')
            ->dir('/')
            ->from('.')
            ->exclude('build')
            ->exclude('cache')
            ->skipSizeEqual()
            ->skipUnmodified()
            ->run();
    }
}
```

## SSL Support
This task supports using FTP over SSl by default. You need the [SSL extension](http://php.net/ssl) for this to work, which isn't always available on Windows. If you want to disable SSL for your task, you can use the `secure()` method:

```php
class RoboFile extends \Robo\Tasks
{
    use RoboFtp\FtpDeploy;

    function deploy()
    {
        $ftp = $this->taskFtpDeploy('host', 'user', 'password')
            ->dir('wwwroot')
            ->from('public')
            ->secure(false)
            ->run();
    }
}
```

Note that some Windows servers do not properly support FTP/S either and may error out when uploading files over SSL. Microsoft has made available a hotfix for this bug, but isn't distributed by default. More information [here](http://support.microsoft.com/kb/2888853/).
