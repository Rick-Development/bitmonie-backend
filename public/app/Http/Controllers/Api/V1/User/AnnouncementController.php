<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Frontend\Announcement;
use App\Models\Frontend\AnnouncementCategory;
use App\Http\Helpers\Response;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('category')->active()->latest()->paginate(20);
        $announcements->getCollection()->transform(function ($item) {
            $lang = $item->data->language->en ?? $item->data->language->{get_default_language_code()} ?? null;
            $image = $item->data->image ?? null;
            return [
                'id' => $item->id,
                'slug' => $item->slug,
                'title' => $lang->title ?? '',
                'description' => $lang->description ?? '',
                'image' => $image ? get_image($image, 'site-section') : null,
                'tags'  => $item->data->tags ?? [],
                'category' => $item->category->name->language->en->name ?? $item->category->name->language->{get_default_language_code()}->name ?? '',
                'created_at' => $item->created_at,
            ];
        });

        return Response::successResponse('Announcements fetched successfully', ['announcements' => $announcements]);
    }

    public function show($slug)
    {
        $item = Announcement::with('category')->active()->where('slug', $slug)->first();
        if(!$item) {
            return Response::errorResponse('Announcement not found', [], 404);
        }

        $lang = $item->data->language->en ?? $item->data->language->{get_default_language_code()} ?? null;
        $image = $item->data->image ?? null;

        $data = [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $lang->title ?? '',
            'description' => $lang->description ?? '',
            'image' => $image ? get_image($image, 'site-section') : null,
            'tags'  => $item->data->tags ?? [],
            'category' => $item->category->name->language->en->name ?? $item->category->name->language->{get_default_language_code()}->name ?? '',
            'created_at' => $item->created_at,
        ];

        return Response::successResponse('Announcement details fetched successfully', ['announcement' => $data]);
    }

    public function categories()
    {
        $categories = AnnouncementCategory::where('status', 1)->latest()->get()->map(function($item) {
            $lang = $item->name->language->en ?? $item->name->language->{get_default_language_code()} ?? null;
            return [
                'id' => $item->id,
                'name' => $lang->name ?? '',
                'created_at' => $item->created_at,
                'status' => $item->status,
            ];
        });
        return Response::successResponse('Categories fetched successfully', ['categories' => $categories]);
    }
}
