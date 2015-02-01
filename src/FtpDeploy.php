<?php
namespace RoboFtp;

trait FtpDeploy
{
    public function taskFtpDeploy($host, $user, $password)
    {
        return new FtpDeployTask($host, $user, $password);
    }
}
