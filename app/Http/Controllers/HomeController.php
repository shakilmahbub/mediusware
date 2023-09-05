<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function home(){
        $transactions = Transaction::orderBy('id','desc')->paginate();
        return view('welcome',compact('transactions'));
    }
}
