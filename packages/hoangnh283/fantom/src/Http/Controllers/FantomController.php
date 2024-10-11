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

            $fantomService = new FantomService();
            $wei = $fantomService->wei;

            if($token == 'FTM'){
                $resultTransfer = $fantomService->transferFantomFTM($fromPrivateKey, $fromAddress, $toAddress, $amount); 
                if(isset($resultTransfer['error'])){
                    return response()->json(['error' => $resultTransfer['error']], 500);
                }
                $transactionInfo = $fantomService->getTransactionByHash($resultTransfer['hash']);

                $balance = $fantomService->getBalance($fromAddress);
            }else{
                $tokenInfo = CoinInfos::where('name', $token)->first();
                if(!$tokenInfo) return response()->json(['error' => 'Token name not found'], 500);

                $resultTransfer = $fantomService->transferFantomToken($fromPrivateKey, $fromAddress, $toAddress, $amount, $tokenInfo);
                if(isset($resultTransfer['error'])){
                    return response()->json(['error' => $resultTransfer['error']], 500);
                }
                $transactionInfo = $fantomService->getTransactionByHash($resultTransfer['hash']);

                $balance = $fantomService->getTokenBalance($fromAddress, $tokenInfo->address, $tokenInfo->decimal);
            }

            $transaction = FantomTransactions::create([
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'amount' => $amount,
                'hash' => $resultTransfer['hash'],
                'gas'=> hexdec($transactionInfo['gas']),
                'gas_price'=> bcdiv(hexdec($transactionInfo['gasPrice']), $wei, 18),
                'fee'=>hexdec($transactionInfo['gas']) * bcdiv(hexdec($transactionInfo['gasPrice']), $wei, 18),
                'nonce'=> hexdec($transactionInfo['nonce']),
                'block_number'=> hexdec($transactionInfo['blockNumber']),
                'status' => 'success',
                'type' => "withdraw",
                'currency' =>  $token,
            ]);
            FantomWithdraw::create([
                'address_id' => $userAddressInfo->id,
                'transaction_id' => $transaction->id,
                'currency' => $token,
                'amount' => $amount,
            ]);
            return response()->json(['success' => true,'transaction' => $transaction, 'balance' => $balance]); 
        } catch (Exception $e) {    
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}