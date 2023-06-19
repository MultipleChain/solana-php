<?php

namespace MultipleChain\Solana;

use Exception;
use MultipleChain\Utils;

final class Transaction
{
    /**
     * @var Provider
     */
    private $provider;
    
    /**
     * Transaction hash
     * @var string
     */
    private $hash;

    /**
     * Transaction data
     * @var object
     */
    private $data;

    /**
     * @param string $hash
     * @param Provider $provider
     * @throws Exception
     */
    public function __construct(string $hash, Provider $provider)
    {
        $this->hash = $hash;
        $this->provider = $provider;
    }

    /**
     * @return string
     */
    public function getHash() : string
    {
        return $this->hash;
    }

    /**
     * @return object|null
     */
    public function getData() : ?object
    {
        return $this->data = $this->provider->getTransaction($this->hash);
    }

    /**
     * @return ?bool
     */
    public function validate() : ?bool
    {
        $this->getData();
        $result = null;

        if ($this->data == null) {
            $result = false;
        } else {
            if (is_null($this->data->meta->err)) {
                $result = true;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @return float
     */
    public function getTransactionAmount() : float 
    {
        $this->getData();
        if (!empty($this->data->meta->preTokenBalances)) {
            $decimals = $this->data->meta->preTokenBalances[0]->uiTokenAmount->decimals;
            $beforeBalance = $this->data->meta->preTokenBalances[0]->uiTokenAmount->uiAmount;
            $afterBalance = $this->data->meta->postTokenBalances[0]->uiTokenAmount->uiAmount;
            $diff = ($beforeBalance - $afterBalance);

            if (!isset($this->data->meta->preTokenBalances[1])) {
                $beforeBalance = $this->data->meta->preTokenBalances[0]->uiTokenAmount->uiAmount;
                $afterBalance = $this->data->meta->postTokenBalances[1]->uiTokenAmount->uiAmount;
                $diff = ($beforeBalance - $afterBalance);
            } elseif ($diff < 0) {
                $decimals = $this->data->meta->preTokenBalances[1]->uiTokenAmount->decimals;
                $beforeBalance = $this->data->meta->preTokenBalances[1]->uiTokenAmount->uiAmount;
                $afterBalance = $this->data->meta->postTokenBalances[1]->uiTokenAmount->uiAmount;
                $diff = ($beforeBalance - $afterBalance);
            }
            
            if ($diff < 0) {
                $diff = floatval(number_format(abs($beforeBalance - $afterBalance), $decimals, '.', ""));
            }
        } else {
            $beforeBalance = $this->data->meta->preBalances[0];
            $afterBalance = $this->data->meta->postBalances[0];
            $diff =($afterBalance - $beforeBalance);
            

            if ($diff < 0) {
                $beforeBalance = $this->data->meta->preBalances[1];
                $afterBalance = $this->data->meta->postBalances[1];
                $diff = Utils::toDec(($afterBalance - $beforeBalance), 9);
            } else {
                $diff = Utils::toDec(($afterBalance - $beforeBalance), 9);
            }
        }

        return $diff;
    }

    /**
     * @param int amount 
     * @return bool
     */
    public function verifyTokenTransferWithData(float $amount) : bool
    {
        if ($this->validate()) {
            $decimals = $this->data->meta->preTokenBalances[0]->uiTokenAmount->decimals;
            $beforeBalance = $this->data->meta->preTokenBalances[0]->uiTokenAmount->uiAmount;
            $afterBalance = $this->data->meta->postTokenBalances[0]->uiTokenAmount->uiAmount;
            $diff = ($beforeBalance - $afterBalance);

            if (!isset($this->data->meta->preTokenBalances[1])) {
                $beforeBalance = $this->data->meta->preTokenBalances[0]->uiTokenAmount->uiAmount;
                $afterBalance = $this->data->meta->postTokenBalances[1]->uiTokenAmount->uiAmount;
                $diff = ($beforeBalance - $afterBalance);
            } elseif (!($diff == $amount)) {
                $decimals = $this->data->meta->preTokenBalances[1]->uiTokenAmount->decimals;
                $beforeBalance = $this->data->meta->preTokenBalances[1]->uiTokenAmount->uiAmount;
                $afterBalance = $this->data->meta->postTokenBalances[1]->uiTokenAmount->uiAmount;
                $diff = ($beforeBalance - $afterBalance);
            }
            
            if (!($diff == $amount)) {
                $diff = floatval(number_format(abs($beforeBalance - $afterBalance), $decimals, '.', ""));
            }

            if (!($diff == $amount)) {
                $amount = floatval(number_format($amount, $decimals, '.', ""));
            }

            return Utils::toString($diff, $decimals) == Utils::toString($amount, $decimals);
        } else {
            return false;
        }
    }

    /**
     * @param int amount 
     * @return bool
     */
    public function verifyCoinTransferWithData(float $amount) : bool 
    {
        if ($this->validate()) {
            $beforeBalance = $this->data->meta->preBalances[0];
            $afterBalance = $this->data->meta->postBalances[0];
            $diff = Utils::toDec(($afterBalance - $beforeBalance), 9);
            
            if (!($diff == $amount)) {
                $beforeBalance = $this->data->meta->preBalances[1];
                $afterBalance = $this->data->meta->postBalances[1];
                $diff = Utils::toDec(($afterBalance - $beforeBalance), 9);
            }

            return Utils::toString($diff, 9) == Utils::toString($amount, 9);
        } else {
            return false;
        }
    }

    /**
     * @param object $config
     * @return bool
     */
    public function verifyTransferWithData(object $config) : bool
    {
        if (isset($config->tokenAddress) && !is_null($config->tokenAddress)) {
            return $this->verifyTokenTransferWithData($config->amount);
        } else {
            return $this->verifyCoinTransferWithData($config->amount);
        }
    }

    /**
     * @return string
     */
    public function getUrl() 
    {
        $node = $this->provider->network->node;
        $url  = $this->provider->network->explorer . "tx/" . $this->hash;
        $url .= $node != 'mainnet-beta' ? '?cluster=' . $node : '';
        return $url;
    }
}