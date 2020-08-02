<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\ApiController;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $transactions = Transaction::all();

        return $this->showAll($transactions);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Transaction $transaction)
    {
        return $this->showOne($transaction);
    }
}
