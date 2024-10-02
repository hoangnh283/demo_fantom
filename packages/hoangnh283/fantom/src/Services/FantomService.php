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
class FantomService
{
    protected $wei = '1000000000000000000'; 
    protected $apiKey = 'XFUA9I7AAGINWFE4XB3UFG5AS4FDRT5QGC';
    // protected $rpcUrl = 'https://rpcapi.fantom.network';
    protected $privateKey = '5bcce244246f48ca0f9fac8ddb9da4648f26ab8aa4ef60a6816c69b2f74f0912';
    protected $toAddress = '0x496d6ae4B693E5eF9cCE6316567C8A8fD1ea34e9';
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
        ],
        'bsc_mainnet' => [
            'chainId' => 56,
            'rpcUrl' => 'https://bsc-dataseed.binance.org',
        ],
        'bsc_testnet' => [
            'chainId' => 97,
            'rpcUrl' => 'https://data-seed-prebsc-1-s1.binance.org:8545',
        ],
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
            return $ftmBalance;
        } else {
            return "Không thể lấy số dư, lỗi: " . $data['message'];
        }
    }

    function requestFantomAirdrop($walletAddress) {
        // URL của Fantom Faucet trên Testnet
        $faucetUrl = "https://faucet.fantom.network/";

        // Dữ liệu POST sẽ được gửi cùng với yêu cầu
        $postData = [
            'address' => $walletAddress,
        ];
        // Khởi tạo cURL
        $ch = curl_init();

        // Cấu hình cURL
        curl_setopt($ch, CURLOPT_URL, $faucetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); // Chuyển dữ liệu thành định dạng URL-encoded
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        // Thực hiện request và lưu kết quả trả về
        $response = curl_exec($ch);

        // Đóng cURL session
        curl_close($ch);

        // Kiểm tra phản hồi
        if ($response === FALSE) {
            return "Request failed!";
        }
        return $response;
    }

    public function transferFantomToken($fromPrivateKey, $fromAddress, $toAddress, $amount, $gas= 30400, $gasPrice= 10000000000000 ) {
        // $amountWei = str_pad(dechex(bcmul($amount, '1000000000000000000')), 64, '0', STR_PAD_LEFT); 
        $amountWei = dechex(bcmul($amount, '1000000000000000000')); 
        // var_dump($amountWei);die;
        $nonce = $this->getTransactionCount($fromAddress);
        $gasHex = '0x' . dechex($gas);
        $gasPriceHex = '0x' . dechex($gasPrice);
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
        
        return $result;
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
    
    function gettxreceiptstatus($txHash) {
        $apiUrl = "https://api.ftmscan.com/api?module=transaction&action=gettxreceiptstatus&txhash=" . $txHash . "&apikey=" . $this->apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data['status'] === "1") {
            return $data['result'];
        } else {
            return "Unable to get transaction status, error: " . $data['message'];
        }
    }

    
}
