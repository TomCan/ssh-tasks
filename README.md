# PHP SSH Task executor
The PHP SSH Task executor (or ssh-tasks) is a library that allows you to execute commands/scripts over a remote SSH
connection from PHP using the ssh2 extention.

# Installation and usage

You can install the library through composer. Then include the autoloader as usual.
```
$ composer require tomcan/ssh-tasks
```

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
    $task = new \TomCan\SshTasks\SshTask($c, 'echo Hello world!');

    var_dump($task->execute());
```

# Authentication
You can authenticate to the remote server using several different authentication schemes. You can do this by passing an
array of associative arrays with the the `type` key set to one of the options below. 

### none
Uses `none` authentication. Should hopefully not work, but it is supported. Although `ssh_auth_none` returns a list 
of supported authentication methods by the server, this value is not passed back or used by the library.
```
$auth = [
    ['type' => 'none'],
];
```
### password
When using `password` authentication, the value of the `password` field in the array will be used as the password for 
ssh password authentication.
```
$auth = [
    ['type' => 'password', 'password' => 'my-sect3t-p@ssw0rd!'],
];
```
### agent
When using `agent` authentication, PHP will try and use the users' ssh-agent active on the machine.
```
$auth = [
    ['type' => 'agent'],
];
```
### pubkey
When using the `pubkey` type, you need to specify both the public as private keys to use for authentication. You can do
this by passing the respective paths using the `pubkey` and `privkey` keys in the array. If the private key file is 
protected with a passphrase, you can pass that passphrase using the `passphrase` key. 
```
$auth = [
    ['type' => 'pubkey', 'pubkey' => '/path/to/id_rsa.pub', 'privkey' => '/path/to/id_rsa', 'passphrase' => 'My other passphrase is much better!'],
];
```

## Using multiple authentication methods
You can add multiple elements to the authentication methods array. The library will try them in the order they appear in
the array, and stops after the first successful method. Note that SSH servers will often close the connection when too 
many authentication attempts have been made.
```
// In order, try to authenticate using the ed25519 key, then the rsa key, and finally password authentication.
$auth = [
    ['type' => 'pubkey', 'pubkey' => '/path/to/id_ed25519.pub', 'privkey' => '/path/to/id_ed25519', 'passphrase' => 'My rsa passphrase is much better!'],
    ['type' => 'pubkey', 'pubkey' => '/path/to/id_rsa.pub', 'privkey' => '/path/to/id_rsa', 'passphrase' => 'My ed25519 passphrase is much better!'],
    ['type' => 'password', 'password' => 'my-sect3t-p@ssw0rd!'],
];
```

# Output
The library supports multiple ways of dealing with the output of the SSH command. You can control this by setting the
`outputMode` parameter on the `SshTask::execute()` method. This parameters defaults to `log`.

## Order of the output
When dealing with multiple output stream (stdout and stderr), the order in which they are read is not guaranteerd as
you can't read both streams at exactly the same time. It is possible that output to stdout or stderr is processed in a
different order than what the output of the command in an actual terminal would be. This is definitely the case when
executing commands that output a lot of data on both streams at the same time.

### log
When using the `log` the output mode, every successful read from either the stdout of stderr steam is added to an 
array where every element contains the timestamp, stream name, and actual data read.
```
[
    [ 1694072955, 'out', "This is output sent to stdout\n"],
    [ 1694072956, 'err', "This is output sent to stderr\n"],
    [ 1694072957, 'out', "This is more output sent to stdout."],
    [ 1694072957, 'out', "Note that there was no linefeed on the previous line.\n"],
]
```

### split
When using the `split` output type, the output array will contain 2 elements. Element 0 will contain the concatenated
output for stdout, where element 1 contains the concatenated output of stderr.
```
[
    "This is output sent to stdout\nThis is more output sent to stdout.Note that there was no linefeed on the previous line.\n",
    "This is output sent to stderr\n",
]
```

### combined
When using the `combined` output type, the command is wrapped in a function with stderr redirected to stdout. It further 
functions in the same way as `split`, but will not have any output in the second element. Since the output redirection
works on the server side, the stdout/stderr output typically always is in the correct order, but you can't tell apart 
stdout from stderr.
```
[
    "This is output sent to stdout\nThis is output sent to stderr\nThis is more output sent to stdout.Note that there was no linefeed on the previous line.\n",
    "",
]
```

### callback
When using the `callback` output type, you can specify a callback function instead of storing the output in an array.
This allows you to receive the output while the command is still running, which can be useful for getting feedback from 
long running commands.  
The function will be called using `call_user_func` and you can set a callback for the output and error stream.
```
    $callbacks = [
        'output' => function($t) { echo 'out> '.$t; },
        'error' => function($t) { echo 'err> '.$t; },
    ];
    $t->execute(true, 'callback', $callbacks));
```
Would result in:
```
out> This is output sent to stdout
err> This is output sent to stderr
out> This is more output sent to stdout.out> Note that there was no linefeed on the previous line.

```
