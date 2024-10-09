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
use Hoangnh283\Fantom\Models\CoinInfos;

class CheckNewTransactions2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $blockNumber;
    private $fantomService;
    private $wei = 1000000000000000000;
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
        $array_address =  FantomAddress::pluck('address')->toArray();
        $contract_addresses = CoinInfos::where('network', 'fantom')->pluck('address')->toArray();
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
                $txData = [];
                // Kiểm tra nếu giao dịch có địa chỉ nhận là địa chỉ của bạn
                if (in_array(strtolower($tx['to']), array_map('strtolower', $array_address))&& hexdec($tx['value']) > 0) {
                    $txData = $this->fantomService->checkFTMTransfer($tx['to'],$this->blockNumber,$this->blockNumber);
                    $txData['currency'] = 'FTM';
                    $txData['value'] = $txData['value']/$this->wei;
                }else{
                    $txData = $this->fantomService->checkFTMTransfer($tx['to'],$this->blockNumber,$this->blockNumber);
                    $txData['currency'] = $txData['tokenSymbol'];
                    $txData['value'] = $txData['value']/pow(10, $txData['tokenDecimal']);
                }

                if (in_array(strtolower($tx['to']), array_map('strtolower', $array_address))) {
                    if(hexdec($tx['value']) > 0){
                        $txData = $this->fantomService->checkFTMTransfer($tx['to'],hexdec($this->blockNumber),hexdec($this->blockNumber));
                        $txData['currency'] = 'FTM';
                        $txData['value'] = $txData['value']/$this->wei;
                        $this->fantomService->getBalance($tx['to']);
                    }else{
                        foreach($contract_addresses as $addressToken){
                            $txData = $this->fantomService->checkTokenTransfer($tx['to'], $addressToken, hexdec($this->blockNumber), hexdec($this->blockNumber));
                            if(!empty($txData)){
                                $txData['currency'] = $txData['tokenSymbol'];
                                $txData['value'] = $txData['value']/pow(10, $txData['tokenDecimal']);
                                $this->fantomService->getTokenBalance($txData['to'], $addressToken, $txData['tokenDecimal']);
                                break;
                            }
                        }
                    }
                }

                if(!empty($txData)){
                    $transaction = FantomTransactions::create([
                        'from_address' => $txData['from'],
                        'to_address' => $txData['to'],
                        'amount' => $txData['value'],
                        'hash' => $txData['hash'],
                        'gas'=> $txData['gas']/$this->wei,
                        'gas_price'=> $txData['gasPrice']/$this->wei,
                        'nonce'=> $txData['nonce'],
                        'block_number'=> $txData['blockNumber'],
                        'status' => 'success',
                        'type' => "deposit",
                        'currency' =>  $txData['currency'],
                    ]);
                    $addressInfo = FantomAddress::where('address', $tx['to'])->first();
                    FantomDeposit::create([
                        'address_id' => $addressInfo->id,
                        'transaction_id' => $transaction->id,
                        'currency' => $txData['currency'],
                        'amount' => $txData['value'],
                    ]);
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
}
