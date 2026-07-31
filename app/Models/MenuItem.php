<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MenuItem extends Model
{
    protected $fillable = [
        'parent_id', 'title', 'icon', 'url', 'menu_group',
        'permission_id', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Build the full menu tree, already filtered down to only the items
     * the given user is allowed to see (based on their role's permissions).
     * Call this once per request (e.g. from a View Composer) and pass the
     * result into the sidebar partial.
     */
    public static function treeForUser(?User $user): Collection
    {
        $allowedPermissionIds = $user && $user->role
            ? $user->role->permissions()->pluck('permissions.id')
            : collect();

        $items = static::active()
            ->orderBy('sort_order')
            ->get();

        $visible = $items->filter(function (MenuItem $item) use ($allowedPermissionIds) {
            // No permission attached = always visible to any logged-in user.
            return is_null($item->permission_id) || $allowedPermissionIds->contains($item->permission_id);
        });

        $byParent = $visible->groupBy('parent_id');

        // A row with no URL is only a heading for its children. Once permission
        // filtering removes every child, that heading has nothing to open — the
        // sidebar would still render it as a javascript:void(0) link that looks
        // clickable but does nothing. Drop those, innermost level first so a
        // heading whose only child was itself pruned goes too.
        $buildBranch = function ($parentId) use (&$buildBranch, $byParent) {
            return ($byParent->get($parentId) ?? collect())
                ->map(function (MenuItem $item) use (&$buildBranch) {
                    $item->setRelation('childrenTree', $buildBranch($item->id));

                    return $item;
                })
                ->reject(fn (MenuItem $item) => blank($item->url) && $item->childrenTree->isEmpty())
                ->values();
        };

        return $buildBranch(null);
    }
}
