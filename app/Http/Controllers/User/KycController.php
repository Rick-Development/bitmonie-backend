<?php

namespace App\Http\Controllers\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Models\Admin\SetupKyc;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    use ControlDynamicInputFields;

    public function index()
    {
        $page_title = "KYC Verification";
        $basic_settings   = BasicSettings::first();
        if($basic_settings['kyc_verification'] == false) return back()->with(['success' => ['Do not need to identity verification!!']]);

        $user = auth()->user();
        $user_kyc = SetupKyc::userKyc()->first();
        if(!$user_kyc) return back()->with(['success' => ['Do not need to identity verification!!']]);
            $kyc_data = $user_kyc->fields;
            $kyc_fields = [];
        if($kyc_data) {
            $kyc_fields = array_reverse($kyc_data);
        }
        return view('user.sections.kyc.index',compact('page_title','user','kyc_fields','kyc_data','user_kyc'));
    }

    public function store(Request $request) {

        $user = auth()->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return back()->with(['success' => ['You are already KYC Verified User']]);

        $user_kyc_fields = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules = $this->generateValidationRules($user_kyc_fields);

        $validated = Validator::make($request->all(),$validation_rules)->validate();
        $get_values = $this->placeValueWithFields($user_kyc_fields,$validated);

        $create = [
            'user_id'       => auth()->user()->id,
            'data'          => json_encode($get_values),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id],$create);
            $user->update([
                'kyc_verified'  => GlobalConst::PENDING,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->route('user.kyc.index')->with(['success' => ['KYC information successfully submitted']]);
    }
}
