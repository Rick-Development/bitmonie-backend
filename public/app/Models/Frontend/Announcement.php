<?php

namespace App\Models\Frontend;

use App\Constants\GlobalConst;
use Illuminate\Database\Eloquent\Model;
use App\Models\Frontend\AnnouncementCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'data'      => 'object',
    ];

    public function getRouteKeyName()
    {
        return "slug";
    }

    public function category() {
        return $this->belongsTo(AnnouncementCategory::class,"announcement_category_id");
    }

    public function scopeActive($query) {
        return $query->where("status",GlobalConst::ACTIVE);
    }
}
