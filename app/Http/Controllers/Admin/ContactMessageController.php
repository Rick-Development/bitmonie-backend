<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ContactRequestExport;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Frontend\ContactRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\websiteSubscribeNotification;

class ContactMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Contact Messages";
        $contact_requests = ContactRequest::orderByDesc("id")->paginate(15);

        return view('admin.sections.contact-request.index',compact('page_title','contact_requests'));
    }

    /**
     * Reply contact messages
     */
    public function reply(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => "required|integer|exists:contact_requests,id",
            'subject'       => "required|string|max:255",
            'message'       => "required|string|max:3000",
        ]);
        if($validator->fails()) return back()->withErrors($validator)->withInput()->with('modal','send-reply');

        $validated = $validator->validate();

        $contact_request = ContactRequest::find($validated['target']);

        try{
            Notification::route("mail",$contact_request->email)->notify(new websiteSubscribeNotification($validated));
            $contact_request->update([
                'reply' => true,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again']]);
        }
        return back()->with(['success' => ['Reply sended successfully!']]);
    }

    /**
     * Export data to excel
     */
    public function export(Request $request)
    {
        return Excel::download(new ContactRequestExport(), 'contact-requests-' . date('d-m-Y') . '.xlsx');
    }

    /**
     * Delete a record from database
     */
    public function delete(Request $request, $mark_delete = false)
    {

        if($mark_delete) {
            $request->validate([
                'mark'    => 'required|array',
                'mark.*'  => 'required|integer|exists:contact_requests,id'
            ]);

            $id = $request->mark;

        }else {
            $request->validate([
                'target'    => 'required|integer|exists:contact_requests,id'
            ]);

            $id = [$request->target];
        }

        ContactRequest::whereIn('id', $id)->delete();

        return back()->with(['success' => ["Message deleted successfully!"]]);
    }

    /**
     * Delete marked record
     */
    public function deleteAll(Request $request)
    {
        return $this->delete($request, true);
    }
}



