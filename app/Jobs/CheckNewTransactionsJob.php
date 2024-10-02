<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Hoangnh283\Fantom\Services\FantomService;
use Illuminate\Support\Facades\Log; // Thêm dòng này để sử dụng Log
use GuzzleHttp\Client;
use Hoangnh283\Fantom\Models\FantomDeposit;
use Hoangnh283\Fantom\Models\FantomAddress;
use Hoangnh283\Fantom\Models\FantomTransactions;
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
    public function __construct()
    {
        $this->fantomService =  new FantomService();
        $this->blockNumber = $this->fantomService->getBlockNumber();
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

        if (isset($blockDetails['transactions']) && is_array($blockDetails['transactions'])) {
            foreach ($blockDetails['transactions'] as $tx) {
                // Kiểm tra nếu giao dịch có địa chỉ nhận là địa chỉ của bạn
                if (in_array(strtolower($tx['to']), array_map('strtolower', $array_address))) {
                    $receiptstatus = $this->fantomService->gettxreceiptstatus($tx['hash']) ? 'success' : 'failed';
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
                }
            }
        }
    }
}
