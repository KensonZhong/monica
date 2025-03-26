<?php

namespace App\Domains\Vault\ManageJournals\Web\ViewHelpers;

use App\Helpers\DateHelper;
use App\Models\Journal;
use App\Models\Post;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TagShowViewHelper
{
    public static function data(Journal $journal, Tag $tag)
    {
        $monthsCollection = self::postsInTag($journal, $tag);

        return [
            'id' => $journal->id,
            'name' => $journal->name,
            'description' => $journal->description,
            'months' => $monthsCollection,
            'years' => JournalShowViewHelper::yearsOfContentInJournal($journal),
            'tags' => JournalShowViewHelper::tags($journal),
            'slices' => JournalShowViewHelper::slices($journal),
            'url' => [
                'journal_metrics' => route('journal_metrics.index', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
                'photo_index' => route('journal.photo.index', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
                'edit' => route('journal.edit', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
                'destroy' => route('journal.destroy', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
                'create' => route('post.create', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
                'slice_index' => route('slices.index', [
                    'vault' => $journal->vault_id,
                    'journal' => $journal->id,
                ]),
            ],
        ];
    }

    public static function postsInTag(Journal $journal, Tag $tag)
    {
        $postsKeyYear = $tag->posts()
            ->orderBy('written_at', 'desc')
            ->with('files')
            ->get()
            ->groupBy(fn(Post $post) => $post->written_at->year);

        $monthsCollection = collect();
        foreach ($postsKeyYear as $year => $posts) {
            $posts = $posts->groupBy(fn(Post $post) => $post->written_at->month);
            for ($month = 12; $month > 0; $month--) {
                $postsCollection = $posts->get($month, collect())
                    ->map(fn(Post $post) => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'excerpt' => $post->excerpt,
                        'written_at_day' => Str::upper(DateHelper::formatShortDay($post->written_at)),
                        'written_at_day_number' => DateHelper::formatDayNumber($post->written_at),
                        'photo' => optional(optional($post)->files)->first() ? [
                            'id' => $post->files->first()->id,
                            'url' => [
//                            'show' => 'https://ucarecdn.com/'.$post->files->first()->uuid.'/-/scale_crop/75x75/smart/-/format/auto/-/quality/smart_retina/',
                                'show' => $post->files->first()->cdn_url,
                            ],
                        ] : null,
                        'url' => [
                            'show' => route('post.show', [
                                'vault' => $journal->vault_id,
                                'journal' => $journal->id,
                                'post' => $post,
                            ]),
                        ],
                    ]);

                $monthsCollection->push([
                    'id' => $month,
                    'month' => Str::upper(Carbon::createFromDate($year, $month, 1)->format('M')),
                    'month_human_format' => DateHelper::formatLongMonthAndYear(Carbon::createFromDate($year, $month, 1)),
                    'posts' => $postsCollection,
                    'count' => $postsCollection->count(),
                    'color' => 'bg-gray-50 dark:bg-gray-900',
                ]);
            }
        }

        // Now we have a collection of months. We need to color each month
        // according to the number of posts they have. The more posts, the darker
        // the color.
        $maxPostsInMonth = 0;
        $maxPosts = 0;
        foreach ($monthsCollection as $month) {
            if ($month['count'] > $maxPostsInMonth) {
                $maxPostsInMonth = $month['count'];
            }

            $maxPosts = $maxPosts + $month['count'];
        }

        foreach ($monthsCollection as $month) {
            if ($month['count'] > 0) {
                $percent = round(($month['count'] / $maxPostsInMonth) * 100);
                // now we round to the nearest 100
                $round = $percent - ($percent % 100 - 100);
                $dark = 1000 - $round;
                $color = "bg-green-$round dark:bg-green-$dark";

                // a really barbaric piece of code so we replace the current collection
                // value with the proper value
                $monthsCollection->transform(function ($item, $key) use ($month, $color) {
                    if ($item['id'] === $month['id']) {
                        $item['color'] = $color;
                    }

                    return $item;
                });
            }
        }

        return $monthsCollection;
    }
}
