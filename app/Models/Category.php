<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
