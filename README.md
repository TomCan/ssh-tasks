# PHP SSH Task executor
The PHP SSH Task executor (or ssh-tasks) is a library that allows you to execute commands/scripts over a remote SSH
connection from PHP using the ssh2 extention.

# Installation
You can install the library through composer.
```composer require tomcan/ssh-tasks```

# Usage
```
<?php
    require 'vendor/autoload.php';

    $auth = [
        [
            'type' => 'password',
            'password' => 'my-secret-password',
        ],
    ];

    $connection = new \TomCan\SshTasks\SshConnection('myserver.domain.tld', 22, 'myuser', $auth);
    $t = new \TomCan\SshTasks\SshTask($c, 'echo Hello world!');

    var_dump($t->execute());
```