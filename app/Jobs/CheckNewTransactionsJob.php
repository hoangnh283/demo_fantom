<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Hoangnh283\Fantom\Services\FantomService;
use Illuminate\Support\Facades\Log; 
use GuzzleHttp\Client;
use Hoangnh283\Fantom\Models\FantomDeposit;
use Hoangnh283\Fantom\Models\FantomAddress;
use Hoangnh283\Fantom\Models\FantomTransactions;
use Hoangnh283\Fantom\Models\CheckedBlock;
class CheckNewTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    // private $fantomService;
    private $blockNumber;
    private $fantomService;
    private $wei = 1000000000000000000;
    private $usdtDecimals = 1000000;
    public function __construct()
    {
        $this->fantomService =  new FantomService();
        // $this->blockNumber = $this->fantomService->getBlockNumber();
        $this->blockNumber = '0x' . dechex($this->getLatestBlock() + 1);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Log::info($this->fantomService);
        $array_address =  FantomAddress::pluck('address')->toArray();

        $client = new Client();

        $blockResponse = $client->post('https://rpcapi.fantom.network', [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_getBlockByNumber', // API để lấy block theo số
                'params' => [$this->blockNumber, true], 
                'id' => 1,
            ]
        ]);
        $blockDetails = json_decode($blockResponse->getBody()->getContents(), true)['result'];
        $transactionBlock = (int)hexdec($this->blockNumber);

        if (isset($blockDetails['transactions']) && is_array($blockDetails['transactions'])) {
            foreach ($blockDetails['transactions'] as $tx) {
                // Kiểm tra nếu giao dịch có địa chỉ nhận là địa chỉ của bạn
                if (in_array(strtolower($tx['to']), array_map('strtolower', $array_address)) && hexdec($tx['value']) > 0) { // check giao dịch FTM 
                    $receiptstatus = $this->waitForTransactionConfirmation($tx['hash']);

                    $transaction = FantomTransactions::create([
                        'from_address' => $tx['from'],
                        'to_address' => $tx['to'],
                        'amount' => hexdec($tx['value'])/$this->wei,
                        'hash' => $tx['hash'],
                        'gas'=> hexdec($tx['gas'])/$this->wei,
                        'gas_price'=> hexdec($tx['gasPrice'])/$this->wei,
                        'nonce'=> hexdec($tx['nonce']),
                        'block_number'=> hexdec($tx['blockNumber']),
                        'status' => $receiptstatus,
                        'type' => "deposit",
                    ]);

                    $addressInfo = FantomAddress::where('address', $tx['to'])->first();
                    FantomDeposit::create([
                        'address_id' => $addressInfo->id,
                        'transaction_id' => $transaction->id,
                        'currency' => 'FTM',
                        'amount' => hexdec($tx['value'])/$this->wei,
                    ]);

                    $this->fantomService->getBalance($tx['to']); // update balance address
                }

                // kiểm tra giao dịch USDT
                $usdtTransaction = $this->checkUsdtTransfer($tx['hash'], $array_address);
                if ($usdtTransaction) {
                    $transaction = FantomTransactions::create([
                        'from_address' => $usdtTransaction['from'],
                        'to_address' => $usdtTransaction['to'],
                        'amount' => $usdtTransaction['amount'],
                        'hash' => $tx['hash'],
                        'gas' => hexdec($tx['gas']) / $this->wei,
                        'gas_price' => hexdec($tx['gasPrice']) / $this->wei,
                        'nonce' => hexdec($tx['nonce']),
                        'block_number' => hexdec($tx['blockNumber']),
                        'status' => $this->waitForTransactionConfirmation($tx['hash']),
                        'type' => "deposit",
                    ]);
    
                    // Lưu vào bảng deposit
                    $addressInfo = FantomAddress::where('address', $usdtTransaction['to'])->first();
                    FantomDeposit::create([
                        'address_id' => $addressInfo->id,
                        'transaction_id' => $transaction->id,
                        'currency' => 'USDT',
                        'amount' => $usdtTransaction['amount'],
                    ]);
                    $this->fantomService->getUsdtBalance($tx['to']); // update balance address
                }
            }
        }
        $this->insertBlockNumber($transactionBlock); 
    }

    public function getLatestBlock()
    {
        $currentBlock = CheckedBlock::latest()->first();
        if (!$currentBlock) {
            $currentBlock = $this->fantomService->getBlockNumber();
            $currentBlock = CheckedBlock::create([
                'block_number' => hexdec($currentBlock)
            ]);
        }

        return $currentBlock->block_number;
    }

    public function insertBlockNumber($newBlockNumber){

       $newBlockNumber = CheckedBlock::create([
            'block_number' => $newBlockNumber,
        ]);

        return $newBlockNumber;
    }

    public function waitForTransactionConfirmation($transactionHash, $minConfirmations = 12, $timeoutSeconds = 60) {
        $startTime = time();
        while (true) {
            if ((time() - $startTime) > $timeoutSeconds) {
                return "pending";
            }
            $receipt = $this->fantomService->getTransactionReceipt($transactionHash);
            if (isset($receipt['blockNumber'])) {
                $currentBlock = hexdec($this->fantomService->getBlockNumber());
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
        $receipt = $this->fantomService->getTransactionReceipt($txHash);
        $usdtContractAddress = '0xcc1b99dDAc1a33c201a742A1851662E87BC7f22C';
        $transferEventSignature = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        if (isset($receipt['logs']) && is_array($receipt['logs'])) {
            foreach ($receipt['logs'] as $log) {
                if (strtolower($log['address']) == strtolower($usdtContractAddress) &&
                    isset($log['topics'][0]) &&
                    strtolower($log['topics'][0]) == strtolower($transferEventSignature)) {
    
                    $from = '0x' . substr($log['topics'][1], 26); // Địa chỉ gửi
                    $to = '0x' . substr($log['topics'][2], 26); // Địa chỉ nhận
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

}
