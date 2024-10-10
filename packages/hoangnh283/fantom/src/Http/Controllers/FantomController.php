<?php
namespace Hoangnh283\Fantom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Hoangnh283\Fantom\Services\FantomService;
use App\Jobs\CheckNewTransactionsJob;
use Hoangnh283\Fantom\Models\FantomAddress;
use Hoangnh283\Fantom\Models\FantomTransactions;
use Exception;
use Hoangnh283\Fantom\Models\FantomWithdraw;
use GuzzleHttp\Client;
use Hoangnh283\Fantom\Models\CheckedBlock;
use Hoangnh283\Fantom\Models\CoinInfos;
class FantomController extends Controller
{
    public function test(Request $request) {
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
        $fromAddress = $request->fromAddress;
        $toAddress = $request->toAddress;
        $amount = $request->amount;
        $token = $request->token;

        try{
            $userAddressInfo = FantomAddress::where('address', $fromAddress)->first();
            $fromPrivateKey = $userAddressInfo->private_key;
            $transaction = FantomTransactions::create([
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'amount' => $amount,
                'hash' => '0x',
                'gas'=> 0,
                'gas_price'=> 0,
                'nonce'=> 0,
                'block_number'=> 0,
                'status' => 'pending',
                'type' => "withdraw",
                'currency' =>  $token,
            ]);

            $fantomService = new FantomService();
            $wei = $fantomService->wei;

            if($token == 'FTM'){

                $resultTransfer = $fantomService->transferFantomFTM($fromPrivateKey, $fromAddress, $toAddress, $amount); 
                $transactionInfo = $fantomService->getTransactionByHash($resultTransfer['hash']);
                $transaction->hash = $resultTransfer['hash'];
                $transaction->gas = bcdiv(hexdec($transactionInfo['gas']), $wei, 18);
                $transaction->gas_price = bcdiv(hexdec($transactionInfo['gasPrice']), $wei, 18);
                $transaction->nonce = hexdec($transactionInfo['nonce']);
                $transaction->block_number = hexdec($transactionInfo['blockNumber']);
                $transaction->status = 'success';
                $transaction->save();

                $balance = $fantomService->getBalance($fromAddress);
            }else{
                
                $tokenInfo = CoinInfos::where('name', $token)->first();
                if(!$tokenInfo) return response()->json(['error' => 'Token name not found'], 500);
                $resultTransfer = $fantomService->transferFantomToken($fromPrivateKey, $fromAddress, $toAddress, $amount, $tokenInfo);

                $transactionInfo = $fantomService->getTransactionByHash($resultTransfer['hash']);
                $transaction->hash = $resultTransfer['hash'];
                $transaction->gas = bcdiv(hexdec($transactionInfo['gas']), $wei, 18);
                $transaction->gas_price = bcdiv(hexdec($transactionInfo['gasPrice']), $wei, 18);
                $transaction->nonce = hexdec($transactionInfo['nonce']);
                $transaction->block_number = hexdec($transactionInfo['blockNumber']);
                $transaction->status = 'success';
                $transaction->save();

                $balance = $fantomService->getTokenBalance($fromAddress, $tokenInfo->address, $tokenInfo->decimal);

            }
            FantomWithdraw::create([
                'address_id' => $userAddressInfo->id,
                'transaction_id' => $transaction->id,
                'currency' => $token,
                'amount' => $amount,
            ]);

            return response()->json(['success' => true,'transaction' => $transaction, 'balance' => $balance]); 
        } catch (Exception $e) {    
            $transaction->status = 'failed';
            $transaction->save();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        return $transaction;
    }

}