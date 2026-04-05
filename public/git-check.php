<?php

$git = shell_exec('which git 2>&1');
$gitVersion = shell_exec('git --version 2>&1');
$whoami = shell_exec('whoami 2>&1');
$home = shell_exec('echo $HOME 2>&1');

echo '<pre>';
echo 'git path: '.$git."\n";
echo 'git version: '.$gitVersion."\n";
echo 'whoami: '.$whoami."\n";
echo 'HOME: '.$home."\n";
echo '</pre>';
