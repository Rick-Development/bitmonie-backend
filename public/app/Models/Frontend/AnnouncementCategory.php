<?php

namespace App\Models\Frontend;

use App\Models\Frontend\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnnouncementCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'name'    => "object",
    ];


    public function announcements() {
        return $this->hasMany(Announcement::class);
    }
}
