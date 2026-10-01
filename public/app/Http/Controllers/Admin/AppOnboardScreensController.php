<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\Language;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Models\Admin\AppOnboardScreens;
use Illuminate\Support\Facades\Validator;

class AppOnboardScreensController extends Controller
{

    /**
     * Display The Onboard Screens Settings Page
     *
     * @return view
     */
    public function onboardScreens() {
        $page_title         = "Onboard Screen";
        $onboard_screens    = AppOnboardScreens::orderByDesc('id')->get();
        $languages          = Language::get();
        return view('admin.sections.app-settings.onboard-screens',compact(
            'languages',
            'page_title',
            'onboard_screens',
        ));
    }


    /**
     * Function for store new onboard screen record
     * @param closer
     */
    public function onboardScreenStore(Request $request) {
        $section_data['heading']['language']  = $this->contentValidate($request,['heading'     => 'required|string'],'onboard-screen-add');
        if($section_data['heading']['language'] instanceof RedirectResponse) {
            return $section_data['heading']['language'];
        }

        $section_data['title']['language']  = $this->contentValidate($request,['title'     => 'required|string'],'onboard-screen-add');
        if($section_data['title']['language'] instanceof RedirectResponse) {
            return $section_data['title']['language'];
        }
        $section_data['details']['language']  = $this->contentValidate($request,['details'     => 'required|string'],'onboard-screen-add');
        if($section_data['details']['language'] instanceof RedirectResponse) {
            return $section_data['details']['language'];
        }

        $validator = Validator::make($request->all(),[
            'image'     => 'required|image|mimes:png,jpg,webp,svg,jpeg',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','onboard-screen-add');
        }

        $section_data['last_edit_by']  = Auth::user()->id;

        if($request->hasFile('image')) {
            try{
                $image = get_files_from_fileholder($request,'image');
                $upload_image = upload_files_from_path_static($image,'app-images',null,true,true);
                $section_data['image'] = $upload_image;
            }catch(Exception $e) {

                return back()->withErrors($validator)->withInput()->with('modal','onboard-screen-add');
            }
        }


        try{
            AppOnboardScreens::create($section_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with(['success' => [__("Onboard Screen Added Successfully!")]]);


    }


    /**
     * Function for update onboard screen status by AJUX request
     */
    public function onboardScreenStatusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'data_target'   => 'required|numeric',
            'status'        => 'required|numeric',
            'input_name'    => 'required|string',
        ]);

        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }

        $validated = $validator->validate();

        $target_id = $validated['data_target'];

        $onboard_screen = AppOnboardScreens::find($target_id);
        if(!$onboard_screen) {
            $error = ['error' => ['Onboard screen not found!']];
            return Response::error($error,null,404);
        }


        // Update Status to Database
        try{
            $onboard_screen->update([
                'status'        => ($onboard_screen->status) ? false : true,
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went worng! Please try again.']];
            return Response::error($error,null,500);
        }

        $success = ['success' => [__('Onboard screen status updated successfully!')]];
        return Response::success($success,null,200);


    }
    /**
     * Function for update specific onboard screen information
     */
    public function onboardScreenUpdate(Request $request) {
        $target = $request->target ?? "";
        $onboard_screen = AppOnboardScreens::find($target);
        if(!$onboard_screen) {
            return back()->withErrors($request->all())->withInput()->with(['warning' => ['Onboard screen not found!']]);
        }
        $section_data['heading']['language']  = $this->contentValidate($request,['heading'      => 'required|string']);
        $section_data['title']['language']  = $this->contentValidate($request,['title'      => 'required|string']);
        $section_data['details']['language']  = $this->contentValidate($request,['details'      => 'required|string']);
        $request->merge(['old_image' => $onboard_screen->image]);

        $validator = Validator::make($request->all(),[
            'target'              => 'required|numeric',
            'screen_image'        => 'nullable|image|mimes:jpg,jpeg,png,svg,webp',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','onboard-screen-edit');
        }

        $validated = $validator->validate();
        $validated = Arr::except($validated,['target','screen_image']);
        if($request->hasFile('screen_image')) {
            try{
                $image = get_files_from_fileholder($request,'screen_image');
                $upload_image = upload_files_from_path_static($image,'app-images',$onboard_screen->image,true,true);
                $section_data['image']  = $upload_image;
            }catch(Exception $e) {
                return back()->withErrors($validator)->withInput()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }
        }

        $validated = replace_array_key($validated,"screen_");

        try{
            $onboard_screen->update($section_data);
        }catch(Exception $e) {
            return back()->withErrors($validator)->withInput()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return back()->with(['success' => [__("Onboard screen information updated successfully!")]]);
    }

    /**
     * Function for delete specific item form record
     * @param  \Illuminate\Http\Request  $request
     */
    public function onboardScreenDelete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required|integer|exists:app_onboard_screens,id',
        ]);
        $validated = $validator->validate();

        try{
            AppOnboardScreens::find($validated['target'])->delete();
        }catch(Exception $e){
            return back()->with(['error' => ['Something went worng! Please try again.']]);
        }

        return back()->with(['success' => ['Screen deleted successfully!']]);
    }
    /**
     * Method for validate request data and re-decorate language wise data
     * @param object $request
     * @param array $basic_field_name
     * @return array $language_wise_data
     */
    public function contentValidate($request,$basic_field_name,$modal = null) {
        $languages = Language::get();

        $current_local = get_default_language_code();
        $validation_rules = [];
        $language_wise_data = [];
        foreach($request->all() as $input_name => $input_value) {
            foreach($languages as $language) {
                $input_name_check = explode("_",$input_name);
                $input_lang_code = array_shift($input_name_check);
                $input_name_check = implode("_",$input_name_check);
                if($input_lang_code == $language['code']) {
                    if(array_key_exists($input_name_check,$basic_field_name)) {
                        $langCode = $language['code'];
                        if($current_local == $langCode) {
                            $validation_rules[$input_name] = $basic_field_name[$input_name_check];
                        }else {
                            $validation_rules[$input_name] = str_replace("required","nullable",$basic_field_name[$input_name_check]);
                        }
                        $language_wise_data[$langCode][$input_name_check] = $input_value;
                    }
                    break;
                }
            }
        }
        if($modal == null) {
            $validated = Validator::make($request->all(),$validation_rules)->validate();
        }else {
            $validator = Validator::make($request->all(),$validation_rules);
            if($validator->fails()) {
                return back()->withErrors($validator)->withInput()->with("modal",$modal);
            }
            $validated = $validator->validate();
        }

        return $language_wise_data;
    }
}
