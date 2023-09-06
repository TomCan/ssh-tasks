<?php

namespace TomCan\SshTasks;

class SshConnection
{
    private string $host;
    private int $port;
    private string $user;
    private array $authMethods;
    private ?array $sshMethods;
    private ?string $fingerprint;

    /** @var resource */
    private $connection;
    private bool $connected = false;

    public function __construct(string $host, int $port, string $user, array $authMethods, string $fingerprint = null, array $sshMethods = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->authMethods = $authMethods;
        $this->fingerprint = $fingerprint;
        $this->sshMethods = $sshMethods;
    }

    public function connect()
    {
        // establish ssh connection
        $callbacks = [
            'disconnect' => array($this, 'cb_disconnect'),
        ];
        $this->connection = ssh2_connect($this->host, $this->port, $this->sshMethods, $callbacks);
        if (!$this->connection) {
            throw new \Exception('Unable to connect to host');
        }

        // check fingerprint if given
        $fp = ssh2_fingerprint($this->connection, (strlen($this->fingerprint ?? '') == 32 ? SSH2_FINGERPRINT_MD5 : SSH2_FINGERPRINT_SHA1) | SSH2_FINGERPRINT_HEX);
        if ($this->fingerprint) {
            if ($fp !== false && 0 !== strcasecmp($this->fingerprint, $fp)) {
                throw new \Exception('SSH fingerprint mismatch');
            }
        }

        $authSucceeded = false;
        foreach ($this->authMethods as $authMethod) {
            switch ($authMethod['type']) {
                case 'none':
                    $authSucceeded = @ssh2_auth_none($this->connection, $this->user);
                    break;
                case 'agent':
                    // suppress PHP Warning on failure
                    $authSucceeded = @ssh2_auth_agent($this->connection, $this->user);
                    break;
                case 'password':
                    // suppress PHP Warning on failure
                    $authSucceeded = @ssh2_auth_password($this->connection, $this->user, $authMethod['password']);
                    break;
                case 'pubkey':
                    // suppress PHP Warning on failure
                    $authSucceeded = @ssh2_auth_pubkey_file($this->connection, $this->user, $authMethod['pubkey'], $authMethod['privkey'], $authMethod['passphrase'] ?? null);
                    break;
            }
            if ($authSucceeded === true) {
                break;
            } else {
                // failed, try next
            }
        }

        if ($authSucceeded !== true) {
            throw new \Exception('Failed to authenticate user');
        }

        $this->connected = true;
    }
    private function cb_disconnect($reason, $message, $language)
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return resource
     */
    public function getConnection()
    {
        return $this->connection;
    }

}
