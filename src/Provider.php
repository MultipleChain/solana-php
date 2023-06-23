<?php

namespace MultipleChain\Solana;

use Exception;

final class Provider
{
    /**
     * @var array
     */
    private $chainIds = [
        "devnet" => 103,
        "testnet" => 102,
        "mainnet-beta" => 101,
    ];
    
    /**
     * @var array
     */
    private $networks = [
        "mainnet" => [
            "node" => "mainnet-beta",
            "name" => "Mainnet",
            "host" => "https://api.mainnet-beta.solana.com/",
            "explorer" => "https://solscan.io/"
        ],
        "testnet" => [
            "node" => "testnet",
            "name" => "Testnet",
            "host" => "https://api.testnet.solana.com",
            "explorer" => "https://solscan.io/"
        ],
        "devnet" => [
            "node" => "devnet",
            "name" => "Devnet",
            "host" => "https://api.devnet.solana.com",
            "explorer" => "https://solscan.io/"
        ]
    ];

    /**
     * @var array
     */
    private $errorCodes = [
        "parse-error" => -32700,
        "invalid-request" => -32600,
        "method-not-found" => -32601,
        "invalid-parameters" => -32602,
        "internal-error" => -32603,
    ];

    /**
     * @var array
     */
    private $allowedMethods = [
        'getAccountInfo', 'getBalance', 'getBlock', 'getBlockHeight', 'getBlockProduction', 'getBlockCommitment', 'getBlocks', 'getBlocksWithLimit', 'getBlockTime', 'getClusterNodes', 'getEpochInfo', 'getEpochSchedule', 'getFeeForMessage', 'getFirstAvailableBlock', 'getGenesisHash', 'getHealth', 'getHighestSnapshotSlot', 'getIdentity', 'getInflationGovernor', 'getInflationRate', 'getInflationReward', 'getLargestAccounts', 'getLatestBlockhash', 'getLeaderSchedule', 'getMaxRetransmitSlot', 'getMaxShredInsertSlot', 'getMinimumBalanceForRentExemption', 'getMultipleAccounts', 'getProgramAccounts', 'getRecentPerformanceSamples', 'getSignaturesForAddress', 'getSignatureStatuses', 'getSlot', 'getSlotLeader', 'getSlotLeaders', 'getStakeActivation', 'getSupply', 'getTokenAccountBalance', 'getTokenAccountsByDelegate', 'getTokenAccountsByOwner', 'getTokenLargestAccounts', 'getTokenSupply', 'getTransaction', 'getTransactionCount', 'getVersion', 'getVoteAccounts', 'isBlockhashValid', 'minimumLedgerSlot', 'requestAirdrop', 'sendTransaction', 'simulateTransaction', 'accountSubscribe', 'accountUnsubscribe', 'logsSubscribe', 'logsUnsubscribe', 'programSubscribe', 'programUnsubscribe', 'signatureSubscribe', 'signatureUnsubscribe', 'slotSubscribe', 'slotUnsubscribe'
    ];

    /**
     * @var object
     */
    public $network;

    /**
     * @var int
     */
    private $randomKey;

    /**
     * @param array|object $options
     * @throws Exception
     */
    public function __construct($options) 
    {
        $options = is_array($options) ? (object) $options : $options;
        $testnet = isset($options->testnet) ? $options->testnet : false;
        $customRpc = isset($options->customRpc) ? $options->customRpc : null;
        $this->network = (object) $this->networks[$testnet ? 'devnet' : 'mainnet'];
        if (!$testnet && $customRpc) {
            $this->network->host = $customRpc;
        }

        $this->randomKey = random_int(0, 99999999);
    }

    /**
     * @param string $method
     * @param array $params
     * @return object
     */
    public function getLastTransactionByReceiver(string $receiver) 
    {
        $requestSignatures = $this->getSignaturesForAddress($receiver, [
            "limit" => 1
        ]);
        
        $transaction = $this->Transaction($requestSignatures[0]->signature);

        return (object) [
            "hash" => $transaction->getHash(),
            "amount" => $transaction->getTransactionAmount()
        ];
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function __call(string $method, array $params = [])
    {
        if (preg_match('/^[a-zA-Z0-9]+$/', $method) === 1) {

            if (!in_array($method, $this->allowedMethods)) {
                throw new Exception('Unallowed method: ' . $method);
            }
            
            return $this->call($method, ...$params);
        } else {
            throw new Exception('Invalid method name');
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function call(string $method, ...$params)
    {
        $curl = curl_init($this->network->host);

        $headers = [
            "Content-Type: application/json"
        ];

        $rpc = $this->buildRpc($method, $params);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $rpc
        ]);

        $response = json_decode(curl_exec($curl));

        curl_close($curl);

        $this->validateResponse($response, $method, $params);

        return $response ? $response->result : null;
    }

    /**
     * @param object $response
     * @param string $method
     * @param array $params
     * @return void
     * @throws Exception
     */
    protected function validateResponse(object $response, string $method, array $params) : void
    {
        if ($response->id !== $this->randomKey) {
            throw new Exception('Invalid response');
        }

        if (isset($response->error)) {
            if ($response->error->code === $this->errorCodes['method-not-found']) {
                throw new Exception("API Error: Method {$method} not found.");
            } else {
                throw new Exception($response->error->message);
            }
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @return string
     */
    public function buildRpc(string $method, array $params) : string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => $this->randomKey,
            'method' => $method,
            'params' => $params,
        ]);
    }
    
    /**
     * @return int
     */
    public function getRandomKey() : int
    {
        return $this->randomKey;
    }
    

    /**
     * @param string $hash
     * @return Transaction
     */
    public function Transaction(string $hash) : Transaction
    {
        return new Transaction($hash, $this);
    }
}