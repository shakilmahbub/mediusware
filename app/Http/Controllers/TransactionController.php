<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    public function createDeposit()
    {
        $users = User::select('id','name')->get();
        $transactions = Transaction::where('transaction_type','deposit')->paginate();
        return view('transaction.deposit',compact('transactions','users'));
    }

    public function storeDeposit(Request $request)
    {
        $this->validate($request,[
            'user' => 'required',
            'amount' => 'required|numeric'
        ]);
        $data = [
            'user_id' => $request->user,
            'amount' => $request->amount,
            'transaction_type' => 'deposit',
            'fee' => 0,
            'date' => today()
        ];
        Transaction::create($data);
        $this->updateBalance($request->user,$request->amount,'deposit');
        session()->flash('success', 'Deposit successful.');
        return redirect()->back();
    }

    public function createWithdrawal()
    {
        $users = User::select('id','name')->get();
        $transactions = Transaction::where('transaction_type','withdrawal')->paginate();
        return view('transaction.withdrawal',compact('transactions','users'));
    }

    public function storeWithdrawal(Request $request)
    {
        $this->validate($request,[
            'user' => 'required',
            'amount' => 'required|numeric'
        ]);
        $user = User::find($request->user);

        $fee = $this->calculateFee($user,$request->amount);
        if ($user->balance < ($request->amount + $fee)){
            return redirect()->back()->with('error', 'Insufficient balance.');
        }
        $data = [
            'user_id' => $request->user,
            'amount' => $request->amount,
            'transaction_type' => 'withdrawal',
            'fee' => $fee,
            'date' => today()
        ];
        Transaction::create($data);
        $this->updateBalance($request->user,$request->amount,'withdrawal',$fee);
        session()->flash('success', 'Withdrawal successful.');
        return redirect()->back();
    }


    private function updateBalance($userid,$amount,$type,$fee = 0)
    {
        $user = User::find($userid);
        if ($type == 'deposit')
        {
            $user->update(['balance' => $user->balance + $amount]);
        }
        elseif($type == 'withdrawal')
        {
            $user->update(['balance' => $user->balance - $amount - $fee]);
        }

        return;
    }


    private function calculateFee($user,$amount){
        $fee = 0;
        if ($user->account_type == 'Individual')
        {
            if (date('l') == 'Friday'){
                $fee = 0;
            }
            else{

                $lasttran = Transaction::select('amount','date')->where('user_id',$user->id)->whereMonth('date', Carbon::now()->month)->get();
                $tatalfree = 0;
                foreach ($lasttran as $trans){
                    if ($trans->amount > 1000){
                        $tatalfree += $tatalfree;
                    }
                    else{
                        $tatalfree += $trans->amount;
                    }
                }
                if ($tatalfree < 5000){
                    if ($amount < 1000){
                        $fee = 0;
                    }
                    if ($amount > 1000){
                        $aditionalamount = $amount - 1000;
                        $fee2 = ($aditionalamount / 100) * 0.015;
                        $fee = $fee2;
                    }
                }
                else{
                    $fee = ($amount / 100) * 0.015;
                }


            }
        }
        elseif($user->account_type == 'Business'){
            $lasttranwithdraw = Transaction::select('amount','date')->where('user_id',$user->id)->sum('amount');
            if ($lasttranwithdraw > 50000){
                $fee = ($amount / 100) * 0.015;
            }
            else{
                $withdrawwithcurrent = $lasttranwithdraw + $amount;
                if ($withdrawwithcurrent < 50000){
                    $fee = ($amount / 100) * 0.025;
                }
                else{
                    $feeable1 = $withdrawwithcurrent - 50000;
                    $fee1 = ($feeable1 / 100) * 0.015;
                    $feeable2 = $amount - $feeable1;
                    $fee2 = ($feeable2 / 100) * 0.025;
                    $fee = $fee1 + $fee2;
                }
            }
        }

        return $fee;
    }

}
