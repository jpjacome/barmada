<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToEditor, HasFactory;

    protected $fillable = ['name', 'editor_id', 'sort_order'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function getEditorNameAttribute()
    {
        return $this->editor ? $this->editor->name : null;
    }
}