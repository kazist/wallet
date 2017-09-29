<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Wallet\Payments\Code\Models;

defined('KAZIST') or exit('Not Kazist Framework');

use Kazist\KazistFactory;
use Payments\Payments\Code\Models\PaymentsModel AS BasePaymentsModel;
use Kazist\Service\Database\Query;

/**
 * Description of MarketplaceModel
 *
 * @author sbc
 */
class PaymentsModel extends BasePaymentsModel {

    public $payment_code = '';

    public function appendSearchQuery($query) {

        $this->ingore_search_query = true;
        return parent:: appendSearchQuery($query);
    }

    public function notificationTransaction($payment_id) {

        $this->completeTransaction($payment_id);
    }

    public function completeTransaction($payment_id) {

        $process_wallet = $this->processWalletPayment($payment_id);
        
        if ($process_wallet) {
            parent::successfulTransaction($payment_id, $this->payment_code);
        } else {
            parent::failTransaction($payment_id, $this->payment_code);
        }
    }

    public function cancelTransaction($payment_id) {
        parent::cancelTransaction($payment_id);
    }

    public function processWalletPayment($payment_id) {

        $factory = new KazistFactory();
        $user = $factory->getUser();

        $gateway = $this->getGatewayByName('wallet');
        $payment = $this->getPaymentById($payment_id);
        $deductions = json_decode($payment->deductions);
        $this->payment_code = $payment->receipt_no;

        $total_earning = $this->getUserTotalEarning($payment);

        if ($total_earning >= $deductions->amount) {

            $payment_obj = new \stdClass();
            $payment_obj->id = $payment->id;
            $payment_obj->code = $payment->receipt_no;
            $payment_obj->receipt_no = $payment->receipt_no;
            $payment_obj->type = 'wallet';
            $payment_obj->gateway_id = $gateway->id;
            $payment_obj->amount_required = $deductions->amount;
            $payment_obj->amount_paid = $deductions->amount;
            $factory->saveRecord('#__payments_payments', $payment_obj);

            $data_obj = new \stdClass();
            $data_obj->user_id = $user->id;
            $data_obj->behalf_user_id = $payment->user_id;
            $data_obj->item_id = $payment->item_id;
            $data_obj->payment_id = $payment->id;
            $data_obj->description = 'Deduction for; ' . $payment->description;
            $data_obj->debit = $payment->amount;
            $data_obj->type = 'wallet';
            $data_obj->source = 'payment';
            $factory->saveRecord('#__payments_transactions', $data_obj);

            return true;
        } else {
            return false;
        }
    }

    public function getUserTotalEarning($payment) {

        $debit = $this->getUserTotalDebit($payment);
        $credit = $this->getUserTotalCredit($payment);

        return $credit - $debit;
    }

    public function getUserTotalDebit($payment) {

        $factory = new KazistFactory();

        $query = new Query();
        $query->select('SUM(fg.debit) as total');
        $query->from('#__payments_transactions', 'fg');
        $query->where('fg.user_id=:user_id');
        $query->setParameter('user_id', $payment->user_id);


        $record = $query->loadObject();

        return $record->total;
    }

    public function getUserTotalCredit($payment) {
        $factory = new KazistFactory();


        $query = new Query();
        $query->select('SUM(fg.credit) as total');
        $query->from('#__payments_transactions', 'fg');
        $query->where('fg.user_id=:user_id');
        $query->setParameter('user_id', $payment->user_id);


        $record = $query->loadObject();

        return $record->total;
    }

}
