<?php

namespace Hoangnh283\Fantom\Services;
// require 'vendor/autoload.php';

use Exception;
use Illuminate\Support\Facades\Log;
use kornrunner\Keccak;
use Elliptic\EC;
use Web3p\EthereumTx\Transaction;
use Web3\Utils;
use Web3\Web3;
use GuzzleHttp\Client;
use Hoangnh283\Fantom\Models\FantomAddress;
// use kornrunner\Ethereum\Transaction;
use kornrunner\Secp256k1;
use Web3p\RLP\RLP;
use Web3\Contract;
use Web3p\EthereumUtil;
use Hoangnh283\Fantom\Models\FantomDeposit;
use Hoangnh283\Fantom\Models\FantomTransactions;
use Hoangnh283\Fantom\Models\FantomBalances;
use Hoangnh283\Fantom\Models\CoinInfos;
class FantomService
{
    public $wei = '1000000000000000000'; 
    protected $apiKey = 'XFUA9I7AAGINWFE4XB3UFG5AS4FDRT5QGC';
    // protected $rpcUrl = 'https://rpcapi.fantom.network';
    private $chainId;
    private $rpcUrl;
    // Mảng chứa thông tin về các mạng
    private static $networks = [
        'fantom_mainnet' => [
            'chainId' => 250,
            'rpcUrl' => 'https://rpcapi.fantom.network',
        ],
        'fantom_testnet' => [
            'chainId' => 4002,
            'rpcUrl' => 'https://rpc.testnet.fantom.network',
        ]
    ];
    public function __construct($networkName = 'fantom_mainnet'){
        if (isset(self::$networks[$networkName])) {
            $this->chainId = self::$networks[$networkName]['chainId'];
            $this->rpcUrl = self::$networks[$networkName]['rpcUrl'];
        } else {
            throw new Exception("Mạng không hợp lệ: $networkName");
        }
    }

    public function createFantomWallet() {
        // Tạo đối tượng ECDSA với secp256k1 (giống như Ethereum và Fantom)
        $ec = new EC('secp256k1');

        // Tạo khóa riêng tư (private key)
        $keyPair = $ec->genKeyPair();
        $privateKey = $keyPair->getPrivate('hex');

        // Tạo khóa công khai (public key)
        $publicKey = $keyPair->getPublic(false, 'hex'); // Không nén (uncompressed)

        // Lấy phần sau của khóa công khai (bỏ '0x04' ở đầu)
        $publicKeyWithoutPrefix = substr($publicKey, 2);

        // Hash bằng Keccak-256
        $publicKeyHash = Keccak::hash(hex2bin($publicKeyWithoutPrefix), 256);

        // Lấy 20 byte cuối (40 ký tự hex) để tạo địa chỉ ví
        $walletAddress = '0x' . substr($publicKeyHash, -40);

        return [
            'privateKey' => $privateKey,
            'walletAddress' => $walletAddress
        ];
    }

    public function getBalance($address) {
        // URL của FTMScan API để lấy số dư
        $apiUrl = "https://api.ftmscan.com/api?module=account&action=balance&address=" . $address . "&apikey=" . $this->apiKey;

        // Sử dụng cURL để gửi yêu cầu GET tới API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data['status'] === "1") {

            $wei = $data['result'];
            // Chuyển đổi từ Wei sang FTM
            $ftmBalance = bcdiv($wei, '1000000000000000000', 18); // Sử dụng bcdiv để chia chính xác

            $addressInfo = FantomAddress::where('address', $address)->first();
            if($addressInfo){
                FantomBalances::updateOrCreate(
                    ['address_id' => $addressInfo->id, 'currency' => 'FTM'], 
                    ['amount' => $ftmBalance] 
                );
            }
            return $ftmBalance;
        } else {
            return "Không thể lấy số dư, lỗi: " . $data['message'];
        }
    }

    public function getUsdtBalance($address)
    {
        $client = new Client();
        $usdtContractAddress = '0xcc1b99dDAc1a33c201a742A1851662E87BC7f22C'; // Địa chỉ hợp đồng USDT trên Fantom
        $balanceOfFunction = '0x70a08231000000000000000000000000' . substr($address, 2); // ABI encoded của hàm balanceOf với địa chỉ ví

        $response = $client->post('https://rpcapi.fantom.network', [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_call',
                'params' => [
                    [
                        'to' => $usdtContractAddress,
                        'data' => $balanceOfFunction
                    ],
                    'latest'
                ],
                'id' => 1,
            ]
        ]);
        $result = json_decode($response->getBody()->getContents(), true)['result'];
        $balance = hexdec($result);
        $usdtBalance = $balance / 1e6;
        $addressInfo = FantomAddress::where('address', $address)->first();

        if($addressInfo){
            FantomBalances::updateOrCreate(
                ['address_id' => $addressInfo->id, 'currency' => 'USDT'], 
                ['amount' => $usdtBalance] 
            );
        }
        
        return $usdtBalance;
    }

    public function getTokenBalance($address, $tokenAddress, $decimals)
    {
        $client = new Client();
        $balanceOfFunction = '0x70a08231000000000000000000000000' . substr($address, 2);
    
        $response = $client->post('https://rpcapi.fantom.network', [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_call',
                'params' => [
                    [
                        'to' => $tokenAddress,
                        'data' => $balanceOfFunction
                    ],
                    'latest'
                ],
                'id' => 1,
            ]
        ]);
    
        $result = json_decode($response->getBody()->getContents(), true)['result'];
        $balance = hexdec($result);
        $tokenBalance = $balance / pow(10, $decimals);
        $addressInfo = FantomAddress::where('address', $address)->first();
        $tokenName = CoinInfos::where('address', $tokenAddress)->pluck('name')->first();
        $tokenName = $tokenName ?? 'Token not found';

        if ($addressInfo) {
            FantomBalances::updateOrCreate(
                ['address_id' => $addressInfo->id, 'currency' => $tokenName], 
                ['amount' => $tokenBalance]
            );
        }
    
        return $tokenBalance;
    }

    public function getGasPrice() {
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_gasPrice',
            'params' => [],
            'id' => 1,
        ]);
        return $this->sendRpcRequest($data);
    }

    public function getEstimateGas($fromAddress, $toAddress, $value, $data = '') {
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_estimateGas',
            'params' => [
                [
                    "from" => $fromAddress,
                    "to" => $toAddress,
                    "value" => $value,
                    "data" => $data
                ],
            ],
            'id' => 1,
        ]);

        $ch = curl_init($this->rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
    
        return json_decode($response, true);
    }

    public function transferFantomFTM($fromPrivateKey, $fromAddress, $toAddress, $amount) {
        // $amountWei = str_pad(dechex(bcmul($amount, '1000000000000000000')), 64, '0', STR_PAD_LEFT); 
        $amountWei = '0x' . dechex(bcmul($amount, '1000000000000000000')); 
        $nonce = $this->getTransactionCount($fromAddress);
        $gasHex = $this->getEstimateGas($fromAddress, $toAddress,$amountWei);
        $gasPriceHex = $this->getGasPrice();
        $transaction = [
            'nonce' => $nonce,
            'from' => $fromAddress,
            'to' => $toAddress,
            'value' => $amountWei,
            'gas' => $gasHex,
            'gasPrice' => $gasPriceHex, // Giá gas mặc định (thay đổi nếu cần)
            'chainId' => $this->chainId,
            // 'data' => '0xa9059cbb' . str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT) . $amountWei,
            'data' => ''
        ];
        $signedTransaction = $this->signTransaction($fromPrivateKey, $transaction);
        $result = $this->sendTransaction($signedTransaction);
        return [
            'gasEstimate' => $gasHex,
            'gasPrice' => $gasPriceHex,
            'nonce' => $nonce,
            'hash' => $result,
        ];
    }

    public function transferFantomToken($fromPrivateKey, $fromAddress, $toAddress, $amount, $tokenInfo) {
        try {
            $contractAddress = $tokenInfo->address;
            $tokenDecimals = $tokenInfo->decimal; 
            $amountToken = bcmul($amount, bcpow('10', $tokenDecimals));
            
            $amountHex = str_pad(dechex($amountToken), 64, '0', STR_PAD_LEFT); // Chuyển đổi số tiền sang hex
            $toAddressHex = str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT); // Địa chỉ người nhận
            $data = '0xa9059cbb' . $toAddressHex . $amountHex; // 0xa9059cbb là hash của hàm transfer(address,uint256)
        
            $nonce = $this->getTransactionCount($fromAddress);
            $getGasHex = $this->getEstimateGas($fromAddress, $contractAddress, '0x0', $data);
            if(!empty($getGasHex['error'])) return ['error'=> $getGasHex['error']["message"]];
            $gasHex = $getGasHex["result"];
            $gasPriceHex = $this->getGasPrice();
        
            $transaction = [
                'nonce' => $nonce,
                'from' => $fromAddress,
                'to' => $contractAddress, // Địa chỉ hợp đồng
                'value' => '0x0',
                'gas' => $gasHex,
                'gasPrice' => $gasPriceHex,
                'data' => $data,
                'chainId' => $this->chainId
            ];
        
            $signedTransaction = $this->signTransaction($fromPrivateKey, $transaction);
        
            $result = $this->sendTransaction($signedTransaction);
        
            return [
                'gasEstimate' => $gasHex,
                'gasPrice' => $gasPriceHex,
                'nonce' => $nonce,
                'hash' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Error' . $e->getMessage());
            return [
                'error'=> $e->getMessage()
            ];
        }
        
    }

    function getTransactionCount($address) {
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionCount',
            'params' => [$address, 'latest'],
            'id' => 1,
        ]);
        return $this->sendRpcRequest($data);
    }
    
    function sendRpcRequest($data) {
        $ch = curl_init($this->rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        if(!empty($result['result'])){
            return $result['result'];
        }else{
            // var_dump($result);die;
            return "Error: " . $result['error']['message'];
        }
    }
    
    function signTransaction($privateKey, $transaction) {
        // $tx = new Transaction([
        //     'nonce' => '0x01',
        //     'from' => '0x50C3e7903F7808b05473245aAAE0598165EF4F93',
        //     'to' => '0x496d6ae4B693E5eF9cCE6316567C8A8fD1ea34e9',
        //     'gas' => '0x76c0',
        //     'gasPrice' => '0x9184e72a000',
        //     'value' => '0x9184e72a',
        //     'chainId' => 250,
        //     // 'data' => $transaction['data'],
        //     'data' => ''
        // ]);
        $tx = new Transaction($transaction);

        $tx->sign($privateKey);
        return $tx->serialize();
    }
    
    function sendTransaction($signedTransaction) {
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_sendRawTransaction',
            'params' => ['0x'.$signedTransaction],
            'id' => 1,
        ]);
    
        return $this->sendRpcRequest($data);
    }

    function gettxreceiptstatus($txHash, $maxRetries = 10, $delay = 1) {
        $apiUrl = "https://api.ftmscan.com/api?module=transaction&action=gettxreceiptstatus&txhash=" . $txHash . "&apikey=" . $this->apiKey;
        $retryCount = 0;
        do {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            if ($data['status'] === "1" && !empty($data['result']['status'])) {
                return $data['result']['status'] == '1' ? true : false;
            }
            sleep($delay);
            $retryCount++;
        } while ($retryCount < $maxRetries);
        return "Unable to get transaction status";
    }

    function getTransactionReceipt($hash){
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionReceipt',
            'params' => [$hash],
            'id' => 1,
        ]);
        $ch = curl_init($this->rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result'];
    }

    public function getTransactionByHash($hash, $maxRetries = 10, $retryDelay = 2){
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionByHash',
            'params' => [$hash],
            'id' => 1,
        ]);
        $attempts = 0; 
        do {
            $ch = curl_init($this->rpcUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $response = curl_exec($ch);
            curl_close($ch);
    
            $result = json_decode($response, true);
            if (isset($result['error'])) {
                return "Error: " . $result['error']['message'];
            }
            if (!is_null($result['result']['blockNumber']) && !is_null($result['result']['blockHash'])) {
                return $result['result'];
            }
            $attempts++;
            sleep($retryDelay);
        } while ($attempts < $maxRetries);
        return $result['result'];
    }

    public function getBlockNumber() {
        $data = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_blockNumber',
            'params' => [],
            'id' => 1,
        ]);
        $ch = curl_init($this->rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result'];
    }

    public function waitForTransactionConfirmation($transactionHash, $minConfirmations = 12, $timeoutSeconds = 10) {
        $startTime = time();
        while (true) {
            if ((time() - $startTime) > $timeoutSeconds) {
                return "pending";
            }
            $receipt = $this->getTransactionReceipt($transactionHash);
            if (isset($receipt['blockNumber'])) {
                $currentBlock = hexdec($this->getBlockNumber());
                $transactionBlock = hexdec($receipt['blockNumber']);
                $confirmations = $currentBlock - $transactionBlock;
    
                if ($confirmations >= $minConfirmations) {
                    if ($receipt['status'] === '0x1') {
                        return "success";
                    } elseif ($receipt['status'] === '0x0') {
                        return "failed";
                    }
                }
            }
            sleep(10);
        }
    }

    private function checkUsdtTransfer($txHash, $array_address){
        $receipt = $this->getTransactionReceipt($txHash);
        $usdtContractAddress = '0xcc1b99dDAc1a33c201a742A1851662E87BC7f22C';
        $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        if (isset($receipt['logs']) && is_array($receipt['logs'])) {
            foreach ($receipt['logs'] as $log) {
                if (strtolower($log['address']) == strtolower($usdtContractAddress) &&
                    isset($log['topics'][0]) &&
                    strtolower($log['topics'][0]) == strtolower($transferEventSignature)) {
    
                    $from = '0x' . substr($log['topics'][1], 26); 
                    $to = '0x' . substr($log['topics'][2], 26);
                    $value = hexdec($log['data']);
    
                    if (in_array(strtolower($to), array_map('strtolower', $array_address)) && $value > 0) {
                        return [
                            'from' => $from,
                            'to' => $to,
                            'amount' => $value / 1000000,
                        ];
                    }
                }
            }
        }
        return null;
    }

    public function checkFTMTransfer($address, $startBlock, $endBlock){
        try {
            $client = new Client();

            $response = $client->get('https://api.ftmscan.com/api', [
                'query' => [
                    'module' => 'account',
                    'action' => 'txlist',
                    'address' => $address,  
                    'startblock' => $startBlock,
                    'endblock' => $endBlock,
                    'apikey' => $this->apiKey  
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            if (isset($responseData['status']) && $responseData['status'] == '1') {
                if (!empty($responseData['result'])) {
                    foreach($responseData['result'] as $value){
                        if(strtolower($value['to']) == strtolower($address) && $value['isError'] == "0" && (int)$value['confirmations'] >= 12){
                            return $value;
                        }
                    }
                    return [];
                } else {
                    return [];
                }
                
            } else {
                Log::error('FTMScan API Error: ' . $responseData['message']);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Error while calling FTMScan API txlist: ' . $e->getMessage());
            return [];
        }
    }

    public function checkTokenTransfer($address, $contractAddress, $startBlock, $endBlock){
        try {
            $client = new Client();

            $response = $client->get('https://api.ftmscan.com/api', [
                'query' => [
                    'module' => 'account',
                    'action' => 'tokentx',
                    'contractaddress' => $contractAddress,
                    'address' => $address,  
                    'startblock' => $startBlock,
                    'endblock' => $endBlock,
                    'apikey' => $this->apiKey  
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            if (isset($responseData['status']) && $responseData['status'] == '1') {
                if (!empty($responseData['result'])) {
                    foreach($responseData['result'] as $value){
                        if(strtolower($value['to']) == strtolower($address) && (int)$value['confirmations'] >= 12){
                            return $value;
                        }
                    }
                    return [];
                } else {
                    return [];
                }
            } else {
                Log::error('FTMScan API Error: ' . $responseData['message']);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Error while calling FTMScan API tokentx: ' . $e->getMessage());
            return [];
        }
    }
    

}
