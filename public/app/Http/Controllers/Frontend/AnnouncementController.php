<?php

namespace App\Http\Controllers\Frontend;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\Language;
use App\Constants\LanguageConst;
use App\Http\Controllers\Controller;
use App\Models\Frontend\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Frontend\AnnouncementCategory;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function categoryIndex()
    {
        $page_title = "Announcement Category";
        $categories = AnnouncementCategory::orderByDesc("id")->get();
        $languages = Language::get();
        return view('admin.sections.setup-sections.announcement.category.index',compact('page_title','categories','languages'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function categoryStore(Request $request)
    {
        $basic_field_name = [
            'name'          => "required|string|max:150",
        ];

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        try{
            AnnouncementCategory::create([
                'name'          => $data,
                'created_at'    => now(),
                'status'        => true,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Category added successfully!']]);
    }

    public function categoryUpdate(Request $request) {
        $validated = $request->validate([
            'target'    => "required|numeric|exists:announcement_categories,id",
        ]);

        $basic_field_name = [
            'name_edit'          => "required|string|max:250",
        ];

        $category = AnnouncementCategory::find($validated['target']);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"category-update");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);

        $data['language']  = $language_wise_data;

        try{
            $category->update([
                'name'      => $data,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }

        return back()->with(['success' => ['Category updated successfully!']]);
    }


    public function categoryStatusUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'status'                    => 'required|boolean',
            'input_name'                => 'required|string',
            'data_target'               => 'required|integer|exists:announcement_categories,id',
        ]);

        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error, null, 400);
        }
        $validated = $validator->validate();

        
        try {
            $category = AnnouncementCategory::find($validated['data_target']);
            if($category) {
                $category->update([
                    'status'    => ($validated['status'] == true) ? false : true,
                ]);
            }
        } catch (Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error, null, 500);
        }

        $success = ['success' => ['Category status updated successfully!']];
        return Response::success($success, null, 200);
    }

    /**
     * Remove the specified resource from record.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function categoryDelete(Request $request)
    {
        $request->validate([
            'target'    => "required|integer|exists:announcement_categories,id",
        ]);

        try{
            $category = AnnouncementCategory::find($request->target);
            if($category) $category->delete();
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        } 

        return back()->with(['success' => ['Category deleted successfully!']]);
    }

    public function announcementIndex() {
        $page_title = "Announcements";
        $announcements = Announcement::orderByDesc("id")->get();

        return view('admin.sections.setup-sections.announcement.index',compact('page_title','announcements'));
    }

    public function announcementCreate() {
        $page_title = "Create New Announcement";
        $categories = AnnouncementCategory::orderByDesc("id")->where("status",GlobalConst::ACTIVE)->get();
        $languages = Language::get();

        return view('admin.sections.setup-sections.announcement.create',compact("page_title","categories","languages"));
    }

    public function announcementStore(Request $request) {
        $basic_field_name = [
            'title'         => "required|string|max:255",
            'description'   => "required|string|max:5000000",
            'tags'          => "required|array",
        ];

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $validated = Validator::make($request->all(),[
            'category'  => "required|integer|exists:announcement_categories,id",
        ])->validate();

        // make slug
        $not_removable_lang = LanguageConst::NOT_REMOVABLE;
        $slug_text = $data['language'][$not_removable_lang]['title'] ?? "";
        if($slug_text == "") {
            $slug_text = $data['language'][get_default_language_code()]['title'] ?? "";
            if($slug_text == "") {
                $slug_text = Str::uuid();
            }
        }
        $slug = Str::slug(Str::lower($slug_text));

        if(Announcement::where('slug',$slug)->exists()) return back()->with(['error' => ['Announcement title is similar. Please update/change this title']]);

        $data['image'] = null;
        if($request->hasFile("image")) {
            $data['image']  = $this->imageValidate($request,"image",null);
        }

        try{
            Announcement::create([
                'slug'                      => $slug,
                'announcement_category_id'  => $validated['category'],
                'data'                      => $data,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong. Please try again']]);
        }

        return redirect()->route('admin.setup.sections.announcement.index')->with(['success' => ['Announcement created successfully!']]);
    }

    public function announcementStatusUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'status'                    => 'required|boolean',
            'input_name'                => 'required|string',
            'data_target'               => 'required|integer|exists:announcements,id',
        ]);

        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error, null, 400);
        }
        $validated = $validator->validate();

        
        try {
            $announcement = Announcement::find($validated['data_target']);
            if($announcement) {
                $announcement->update([
                    'status'    => ($validated['status'] == true) ? false : true,
                ]);
            }
        } catch (Exception $e) {
            $error = ['error' => ['Something went wrong!. Please try again.']];
            return Response::error($error, null, 500);
        }

        $success = ['success' => ['Announcement status updated successfully!']];
        return Response::success($success, null, 200);
    }

    public function announcementDelete(Request $request) {
        $request->validate([
            'target'    => "required|integer|exists:announcements,id"
        ]);

        try{
            $announcement = Announcement::find($request->target);
            if($announcement) {
                $image_name = $announcement->data?->image ?? null;
                if($image_name) {
                    $image_link = get_files_path('site-section') . "/" . $image_name;
                    delete_file($image_link);
                }
                $announcement->delete();
            }
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong. Please try again']]);
        }
        return back()->with(['success' => ['Announcement deleted successfully!']]);
    }

    public function announcementEdit($id) {
        $announcement = Announcement::find($id);
        if(!$announcement) return back()->with(['error' => ['Announcement does\'t exists!']]);
        $page_title = "Announcement Edit";
        $languages = Language::get();
        $categories = AnnouncementCategory::where("status",GlobalConst::ACTIVE)->orderByDesc("id")->get();
        return view('admin.sections.setup-sections.announcement.edit',compact("page_title","announcement","languages","categories"));
    }

    public function announcementUpdate(Request $request,$id) {

        $announcement = Announcement::find($id);
        if(!$announcement) return back()->with(['error' => ['Announcement does\'t exists!']]);

        $basic_field_name = [
            'title'         => "required|string|max:255",
            'description'   => "required|string|max:5000000",
            'tags'          => "required|array",
        ];

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $validated = Validator::make($request->all(),[
            'category'  => "required|integer|exists:announcement_categories,id",
        ])->validate();

        $data['image'] = $announcement->data?->image ?? null;
        if($request->hasFile("image")) {
            $data['image']  = $this->imageValidate($request,"image",$data['image']);
        }

        try{
            $announcement->update([
                'announcement_category_id'  => $validated['category'],
                'data'                      => $data,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong. Please try again']]);
        }

        return redirect()->route('admin.setup.sections.announcement.index')->with(['success' => ['Announcement updated successfully!']]);
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

    /**
     * Method for validate request image if have
     * @param object $request
     * @param string $input_name
     * @param string $old_image
     * @return boolean|string $upload
     */
    public function imageValidate($request,$input_name,$old_image = null) {
        if($request->hasFile($input_name)) {
            $image_validated = Validator::make($request->only($input_name),[
                $input_name         => "image|mimes:png,jpg,webp,jpeg,svg",
            ])->validate();

            $image = get_files_from_fileholder($request,$input_name);
            $upload = upload_files_from_path_dynamic($image,'site-section',$old_image);
            return $upload;
        }

        return false;
    }
}
