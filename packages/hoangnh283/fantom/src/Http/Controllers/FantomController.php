<?php
namespace Hoangnh283\Fantom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Hoangnh283\Fantom\Services\FantomService;
use App\Jobs\CheckNewTransactionsJob;
use Hoangnh283\Fantom\Models\FantomAddress;
use Hoangnh283\Fantom\Models\FantomTransactions;
use Hoangnh283\Fantom\Models\FantomWithdraw;
use GuzzleHttp\Client;

class FantomController extends Controller
{
    public function test(Request $request) {
        $fantomService =  new FantomService();
        // $test = $fantomService->checkNewTransactions();
        // $balance = $fantomService->transferFantomToken('5bcce244246f48ca0f9fac8ddb9da4648f26ab8aa4ef60a6816c69b2f74f0912','0x50C3e7903F7808b05473245aAAE0598165EF4F93', '0x496d6ae4B693E5eF9cCE6316567C8A8fD1ea34e9', 0.001);

        // var_dump($test);die;
        CheckNewTransactionsJob::dispatch();
        // CheckNewTransactionsJob::dispatch($fantomService)->delay(now()->addSeconds(10));
        return true;
    }
    public function createWallet(Request $request) {

        $fantomService =  new FantomService();
        $result =  $fantomService->createFantomWallet();
        
        $address = FantomAddress::create([
            'address' => $result['walletAddress'],
            'private_key' => $result['privateKey']
        ]);

        return $address;
    }

    public function withdraw(Request $request) {

        $fromPrivateKey = '5bcce244246f48ca0f9fac8ddb9da4648f26ab8aa4ef60a6816c69b2f74f0912';
        $fromAddress = '0x50C3e7903F7808b05473245aAAE0598165EF4F93';
        $toAddress = '0x496d6ae4B693E5eF9cCE6316567C8A8fD1ea34e9';
        $amount = 0.001;
        $fantomService =  new FantomService();
        $hash = $fantomService->transferFantomToken($fromPrivateKey, $fromAddress, $toAddress, $amount);

        return $hash;
    }
}