<?php

namespace Hoangnh283\Fantom\Console\Commands;

use Illuminate\Console\Command;
use Hoangnh283\Fantom\Services\FantomService;
use GuzzleHttp\Client;
class CheckNewTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:check-new-transactions';
    protected $rpcUrl = 'https://rpcapi.fantom.network';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // return Command::SUCCESS;
        $this->checkNewTransactions();
    }

    public function checkNewTransactions(){
        $client = new Client();
        // Lấy block mới nhất
        $response = $client->post($this->rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_blockNumber', // API để lấy block mới nhất
                'params' => [],
                'id' => 1,
            ]
        ]);

        $blockNumber = json_decode($response->getBody()->getContents(), true)['result'];
        // Lấy chi tiết block
        $blockResponse = $client->post($this->rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_getBlockByNumber', // API để lấy block theo số
                'params' => [$blockNumber, true], // true để lấy chi tiết các giao dịch
                'id' => 1,
            ]
        ]);

        $blockDetails = json_decode($blockResponse->getBody()->getContents(), true)['result'];

        if (isset($blockDetails['transactions']) && is_array($blockDetails['transactions'])) {
            foreach ($blockDetails['transactions'] as $tx) {
                // var_dump($tx['to']);
                $this->info('Checked block: ' . $tx['to']);
                // Kiểm tra nếu giao dịch có địa chỉ nhận là địa chỉ của bạn
                if (strtolower($tx['to']) === strtolower('0xYourWalletAddress')) {
                    // Ghi lại thông tin giao dịch vào cơ sở dữ liệu hoặc gửi thông báo
                    // $this->storeTransaction($tx);
                }
            }
        }
        $this->info('Checked block: ' . $blockNumber);
    }
}
