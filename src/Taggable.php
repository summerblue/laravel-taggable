<?php

namespace EstGroupe\Taggable;

use EstGroupe\Taggable\Contracts\TaggingUtility;
use EstGroupe\Taggable\Events\TagAdded;
use EstGroupe\Taggable\Events\TagRemoved;
use EstGroupe\Taggable\Model\Tagged;
use Illuminate\Database\Eloquent\Collection;

/**
 * Copyright (C) 2014 Robert Conner
 */
trait Taggable
{
    /** @var \EstGroupe\Taggable\Contracts\TaggingUtility * */
    static $taggingUtility;

    /**
     * Temp storage for auto tag
     *
     * @var mixed
     * @access protected
     */
    protected $autoTagTmp;

    /**
     * Track if auto tag has been manually set
     *
     * @var boolean
     * @access protected
     */
    protected $autoTagSet = false;

    /**
     * Boot the soft taggable trait for a model.
     *
     * @return void
     */
    public static function bootTaggable()
    {
        if (static::untagOnDelete()) {
            static::deleting(function ($model) {
                $model->untag();
            });
        }

        static::saved(function ($model) {
            $model->autoTagPostSave();
        });

        static::$taggingUtility = app(TaggingUtility::class);
    }

    /**
     * Tag 类型
     * @return mixed
     */
    public function tagType()
    {
        return "";
    }

    /**
     * Return collection of tagged rows related to the tagged model
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function tagged()
    {
        return $this->morphMany('EstGroupe\Taggable\Model\Tagged', 'taggable')->with('tag');
    }

    public function tags()
    {
        return $this->morphToMany(static::$taggingUtility->tagModelString(), 'taggable');
    }

    /**
     * Set the tag names via attribute, example $model->tag_names = 'foo, bar';
     *
     * @param string $value
     */
    public function getTagNamesAttribute($value)
    {
        return implode(', ', $this->tagNames());
    }

    /**
     * Perform the action of tagging the model with the given string
     *
     * @param $tagName string or array
     */
    public function tag($tagNames)
    {
        if (!is_array($tagNames)) {
            $tagNames = func_get_args();
        }
        $tagNames = static::$taggingUtility->makeTagArray($tagNames);

        foreach ($tagNames as $tagName) {
            $this->addTag($tagName);
        }
    }

    /**
     * Return array of the tag names related to the current model
     *
     * @return array
     */
    public function tagNames()
    {
        return $this->tags()->pluck('name')->all();
    }

    /**
     * Return array of the tag slugs related to the current model
     *
     * @return array
     */
    public function tagSlugs()
    {
        return $this->tags()->pluck('slug')->all();
    }

    /**
     * Remove the tag from this model
     *
     * @param $tagName string or array (or null to remove all tags)
     */
    public function untag($tagNames = null)
    {
        if (is_null($tagNames)) {
            $tagNames = $this->tagNames();
        }

        $tagNames = static::$taggingUtility->makeTagArray($tagNames);

        foreach ($tagNames as $tagName) {
            $this->removeTag($tagName);
        }

        if (static::shouldDeleteUnused()) {
            static::$taggingUtility->deleteUnusedTags();
        }
    }

    /**
     * Replace the tags from this model
     *
     * @param $tagName string or array
     */
    public function retag($tagNames)
    {
        if (!is_array($tagNames)) {
            $tagNames = func_get_args();
        }
        $tagNames = static::$taggingUtility->makeTagArray($tagNames);
        $currentTagNames = $this->tagNames();

        $deletions = array_diff($currentTagNames, $tagNames);
        $additions = array_diff($tagNames, $currentTagNames);

        $this->untag($deletions);

        foreach ($additions as $tagName) {
            $this->addTag($tagName);
        }
    }

    /**
     * Filter model to subset with the given tags
     *
     * @param $tagNames array|string
     */
    public function scopeWithAllTags($query, $tagNames)
    {
        if (!is_array($tagNames)) {
            $tagNames = func_get_args();
            array_shift($tagNames);
        }
        $tagNames = static::$taggingUtility->makeTagArray($tagNames);

        $model = static::$taggingUtility->tagModelString();
        $tagids = $model::byTagNames($tagNames)->pluck('id')->all();

        $className = $query->getModel()->getMorphClass();
        $primaryKey = $this->getKeyName();

        $tagid_count = count($tagids);
        if ($tagid_count > 0) {
            $ids = Tagged::where('taggable_type', $className)
                ->whereIn('tag_id', $tagids)
                ->whereRaw('`tag_id` in (' . implode(',', $tagids) . ') group by taggable_id having count(taggable_id) =' . $tagid_count)
                ->pluck('taggable_id');

            $query->whereIn($this->getTable() . '.' . $primaryKey, $ids);
        }

        return $query;
    }

    /**
     * Filter model to subset with the given tags
     *
     * @param $tagNames array|string
     */
    public function scopeWithAnyTag($query, $tagNames)
    {
        if (!is_array($tagNames)) {
            $tagNames = func_get_args();
            array_shift($tagNames);
        }
        $tagNames = static::$taggingUtility->makeTagArray($tagNames);

        $model = static::$taggingUtility->tagModelString();
        $tagids = $model::byTagNames($tagNames)->pluck('id')->all();

        $className = $query->getModel()->getMorphClass();
        $primaryKey = $this->getKeyName();

        $tags = Tagged::whereIn('tag_id', $tagids)
            ->where('taggable_type', $className)
            ->pluck('taggable_id');

        return $query->whereIn($this->getTable() . '.' . $primaryKey, $tags);
    }

    /**
     * Adds a single tag
     *
     * @param $tagName string
     */
    private function addTag($tagName)
    {
        $model = static::$taggingUtility->tagModelString();
        $tag = $model::byTagName($tagName)->first();

        if ($tag) {
            // If tag is exists, do not create
            $count = $this->tagged()->where('tag_id', '=', $tag->id)->take(1)->count();
            if ($count >= 1) {
                return;
            } else {
                $this->tags()->attach($tag->id);
            }
        } else {
            // If tag is not exists, create tag and attach to object
            $tag = new $model;
            $tag->name = $tagName;
            $tag->save();

            $this->tags()->attach($tag->id);
        }
        static::$taggingUtility->incrementCount($tag, 1);

        if (config('taggable.is_tagged_label_enable')
            && $this->is_tagged != 'yes'
        ) {
            $this->is_tagged = 'yes';
            $this->save();
        }

        unset($this->relations['tagged']);
        event(new TagAdded($this));
    }

    /**
     * Removes a single tag
     *
     * @param $tagName string
     */
    private function removeTag($tagName)
    {
        $tag = $this->tags()->byTagName($tagName)->first();

        if ($tag) {
            $this->tags()->detach($tag->id);
            static::$taggingUtility->decrementCount($tag, 1);
        }

        if (config('taggable.is_tagged_label_enable')
            && $this->is_tagged != 'no'
            && $this->tags()->count() <= 0
        ) {
            $this->is_tagged = 'no';
            $this->save();
        }

        unset($this->relations['tagged']);
        event(new TagRemoved($this));
    }

    /**
     * Return an array of all of the tags that are in use by this model
     *
     * @return Collection
     */
    public static function existingTags()
    {
        $tags_table_name = config('taggable.tags_table_name');

        return Tagged::distinct()
            ->join($tags_table_name, 'tag_id', '=', $tags_table_name . '.id')
            ->where('taggable_type', '=', (new static)->getMorphClass())
            ->orderBy('tag_id', 'ASC')
            ->get(array($tags_table_name . '.slug as slug', $tags_table_name . '.name as name', $tags_table_name . '.count as count'));
    }

    /**
     * Should untag on delete
     */
    public static function untagOnDelete()
    {
        return isset(static::$untagOnDelete)
            ? static::$untagOnDelete
            : config('taggable.untag_on_delete');
    }

    /**
     * Delete tags that are not used anymore
     */
    public static function shouldDeleteUnused()
    {
        return config('taggable.delete_unused_tags');
    }

    /**
     * Set tag names to be set on save
     *
     * @param mixed $value Data for retag
     *
     * @return void
     *
     * @access public
     */
    public function setTagNamesAttribute($value)
    {
        $this->autoTagTmp = $value;
        $this->autoTagSet = true;
    }

    /**
     * AutoTag post-save hook
     *
     * Tags model based on data stored in tmp property, or untags if manually
     * set to falsey value
     *
     * @return void
     *
     * @access public
     */
    public function autoTagPostSave()
    {
        if ($this->autoTagSet) {
            if ($this->autoTagTmp) {
                $this->retag($this->autoTagTmp);
            } else {
                $this->untag();
            }
        }
    }

    /**
     * by @CJ
     * Sync tags with tag_id array
     *
     * @param $tag_ids tag_id array
     */
    public function tagWithTagIds($tag_ids = [])
    {
        if (count($tag_ids) <= 0) {
            return;
        }

        $model = static::$taggingUtility->tagModelString();
        $tag_names = $model::byTagIds($tag_ids)->pluck('name')->all();

        $this->retag($tag_names);
    }


}
