<?php

namespace App\Http\Controllers\Frontend;

use Exception;
use Illuminate\Http\Request;
use App\Models\Admin\Language;
use App\Models\Admin\UsefulLink;
use App\Models\Frontend\Subscribe;
use App\Http\Controllers\Controller;
use App\Models\Frontend\Announcement;
use App\Models\Frontend\ContactRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\Frontend\AnnouncementCategory;
use App\Providers\Admin\BasicSettingsProvider;

class IndexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(BasicSettingsProvider $basic_settings)
    {
        return abort(404);
        // return view('frontend.index');
    }
    /**
     * Method for view about page
     * @return view
     */
    public function about(BasicSettingsProvider $basic_settings){
        return abort(404);
        $page_title = "About";
        return view('frontend.pages.about',compact(
            'page_title'
        ));
    }
    /**
     * Method for view services page
     * @return view
     */
    public function services(BasicSettingsProvider $basic_settings){
        return abort(404);
        $page_title = "Services";
        return view('frontend.pages.services',compact(
            'page_title'
        ));
    }
    /**
     * Method for view web journals page
     * @return view
     */
    public function webJournals(BasicSettingsProvider $basic_settings){
        return abort(404);
        $page_title      = "Web Journals";
        $journals        = Announcement::with(['category'])->where('status',true)->latest()->paginate(10);

        return view('frontend.pages.journal',compact(
            'page_title',
            'journals'
        ));
    }
    /**
     * Method for journal details page
     * @param $slug
     * @param Illuminate\Http\Request $request
     */
    public function journalDetails($slug){
        return abort(404);
        $page_title             = "Journal Details";
        $journal                = Announcement::where('slug',$slug)->first();
        if(!$journal) return back()->with(['error' => ['Something went wrong! Please try again.']]);
        $category               = AnnouncementCategory::withCount('announcements')->where('status',true)->get();
        $recent_posts           = Announcement::where('status',true)->where('slug','!=',$slug)->get();

        return view('frontend.pages.journal-details',compact(
            'page_title',
            'journal',
            'category',
            'recent_posts'
        ));

    }
    /**
     * Method for journal details page
     * @param $slug
     * @param Illuminate\Http\Request $request
     */
    public function journalCategory($id){
        return abort(404);
        $page_title             = "Journal Details";
        $blog_category          = AnnouncementCategory::where('id',$id)->first();
        if(!$blog_category) abort(404);
        $blogs                  = Announcement::where('announcement_category_id',$blog_category->id)->latest()->paginate(6);

        return view('frontend.pages.journal-category',compact(
            'page_title',
            'blogs',
            'blog_category',
        ));
    }
    /**
     * Method for view contact page 
     * @return view
     */
    public function contact(){
        return abort(404);
        $page_title     = "Contact";

        return view('frontend.pages.contact',compact('page_title'));
    }

    public function subscribe(Request $request) {
        return abort(404);
         $validator      = Validator::make($request->all(),[
            'email'     => "required|string|email|max:255|unique:subscribes",
        ]);
        if($validator->fails()) return redirect(url()->previous() . '/#subscribe-form')->withErrors($validator)->withInput();
        $validated = $validator->validate();
        try{
            Subscribe::create([
                'email'         => $validated['email'],
                'created_at'    => now(),
            ]);
        }catch(Exception $e) {
            return redirect('/#subscribe-form')->with(['error' => ['Failed to subscribe. Try again']]);
        }
        return redirect(url()->previous() . '/#subscribe-form')->with(['success' => ['Subscription successful!']]);
    }

    public function contactMessageSend(Request $request) {
 return abort(404);
        $validated = Validator::make($request->all(),[
            'name'      => "required|string|max:255",
            'email'     => "required|email|string|max:255",
            'message'   => "required|string|max:5000",
        ])->validate();

        try{
            ContactRequest::create($validated);
        }catch(Exception $e) {
            return back()->with(['error' => ['Failed to send message. Please Try again']]);
        }

        return back()->with(['success' => ['Message send successfully!']]);
    }

    public function usefulLink($slug) {
        return abort(404);
        $useful_link = UsefulLink::where("slug",$slug)->first();
        if(!$useful_link) abort(404);

        $basic_settings = BasicSettingsProvider::get();

        $app_local = get_default_language_code();
        $page_title = $useful_link->title?->language?->$app_local?->title ?? $basic_settings->site_name;

        return view('frontend.pages.useful-link',compact('page_title','useful_link'));
    }


    public function languageSwitch(Request $request) {
        return abort(404);
        $code = $request->target;
        $language = Language::where("code",$code)->first();
        if(!$language) {
            return back()->with(['error' => ['Oops! Language Not Found!']]);
        }
        Session::put('local',$code);
        Session::put('local_dir',$language->dir);

        return back()->with(['success' => ['Language Switch to ' . $language->name ]]);
    }
}
