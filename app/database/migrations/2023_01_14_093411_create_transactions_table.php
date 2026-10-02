<?php

use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum("type",[
                PaymentGatewayConst::TYPEADDMONEY,
                PaymentGatewayConst::TYPEMONEYOUT,
                PaymentGatewayConst::TYPEWITHDRAW,
                PaymentGatewayConst::TYPECOMMISSION,
                PaymentGatewayConst::TYPEBONUS,
                PaymentGatewayConst::TYPETRANSFERMONEY,
                PaymentGatewayConst::TYPEMONEYEXCHANGE,
                PaymentGatewayConst::TYPEADDSUBTRACTBALANCE,
                PaymentGatewayConst::TYPEMAKEPAYMENT,
                PaymentGatewayConst::TYPECAPITALRETURN,
                PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER,
                PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER,
                PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER,
                PaymentGatewayConst::TYPEVIRTUALCARD,
                PaymentGatewayConst::SALARYDISBURSEMENT
            ]);
            $table->string('trx_id')->comment('Transaction ID');
            $table->string('salary_disbursement_id')->nullable();
            $table->enum('user_type',[
                GlobalConst::USER,
                GlobalConst::ADMIN,
            ])->nullable()->comment("transaction creator");
            $table->unsignedBigInteger('user_id')->nullable()->comment("transaction creator id");
            $table->unsignedBigInteger('wallet_id')->nullable()->comment("transaction creator wallet it");
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('payment_gateway_currency_id')->nullable();
            $table->decimal('request_amount', 28, 8)->comment('add money: user wallet balance, money transfer: send amount, money out: withdraw wallet amount');
            $table->string('request_currency')->comment("In add money user wallet currency, money transfer receiver currency");
            $table->decimal('exchange_rate', 28, 8)->nullable();
            $table->decimal('percent_charge', 28, 8)->nullable();
            $table->decimal('fixed_charge', 28, 8)->nullable();
            $table->decimal('total_charge', 28, 8)->nullable();
            $table->decimal('total_payable', 28, 8)->nullable();
            $table->decimal('receive_amount', 28, 8)->nullable()->comment('add money: user wallet balance, money transfer: receiver amount, money out: user receive amount using manual info');
            $table->enum('receiver_type',[
                GlobalConst::USER,
                GlobalConst::ADMIN,
            ])->nullable()->comment("Uses maybe money transfer, make payment");
            
            $table->unsignedBigInteger('receiver_id')->nullable()->comment("Uses maybe money transfer, make payment");
            $table->decimal('available_balance', 28, 8);
            $table->string('payment_currency')->nullable()->comment('user payment currency (wallet/gateway)');
            $table->string('remark')->nullable();
            $table->text('details')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->enum("attribute",[
                PaymentGatewayConst::SEND,
                PaymentGatewayConst::RECEIVED,
            ])->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('callback_ref')->nullable();
            $table->timestamps();

            $table->foreign("wallet_id")->references("id")->on("user_wallets")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("payment_gateway_currency_id")->references("id")->on("payment_gateway_currencies")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("admin_id")->references("id")->on("admins")->onDelete("cascade")->onUpdate("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
